"""MariaDB PDO JSON bridge and PHP CLI execution helper for EduShare E2E tests."""

from __future__ import annotations

import json
import os
import re
import subprocess
from dataclasses import dataclass
from typing import Any, Mapping, Sequence

from .config import DB_CONFIG, PHP_BIN, PROJECT_ROOT


class DbError(RuntimeError):
    """Raised when PDO execution or PHP CLI invocation fails."""


@dataclass
class CliResult:
    """Result of executing a PHP CLI script."""

    returncode: int
    stdout: str
    stderr: str


_PHP_PDO_BRIDGE = r"""
$raw = stream_get_contents(STDIN);
$req = json_decode($raw, true);
if (!is_array($req) || !isset($req['db'], $req['sql'])) {
    fwrite(STDOUT, json_encode(['ok' => false, 'error' => 'Invalid bridge payload', 'code' => 'BRIDGE_INPUT']));
    exit(1);
}
$db = $req['db'];
$sql = (string)$req['sql'];
$params = $req['params'] ?? [];
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'] ?? 'localhost',
        (int)($db['port'] ?? 3306),
        $db['database'] ?? 'edushare',
        $db['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, (string)($db['user'] ?? 'root'), (string)($db['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $stmt = $pdo->prepare($sql);
    if (is_array($params) && count($params) > 0) {
        $isList = array_is_list($params);
        foreach ($params as $key => $val) {
            if ($val === null) {
                $type = PDO::PARAM_NULL;
                $bound = null;
            } elseif (is_bool($val)) {
                $type = PDO::PARAM_INT;
                $bound = $val ? 1 : 0;
            } elseif (is_int($val)
            ) {
                $type = PDO::PARAM_INT;
                $bound = $val;
            } else {
                $type = PDO::PARAM_STR;
                $bound = (string)$val;
            }
            if ($isList) {
                $stmt->bindValue(((int)$key) + 1, $bound, $type);
            } else {
                $paramName = str_starts_with((string)$key, ':') ? (string)$key : ':' . (string)$key;
                $stmt->bindValue($paramName, $bound, $type);
            }
        }
    }
    $stmt->execute();
    $rows = $stmt->columnCount() > 0 ? $stmt->fetchAll() : null;
    $out = [
        'ok' => true,
        'rows' => $rows,
        'rowCount' => $stmt->rowCount(),
        'lastInsertId' => $pdo->lastInsertId(),
    ];
    $json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        throw new RuntimeException('json_encode failed: ' . json_last_error_msg());
    }
    fwrite(STDOUT, $json);
} catch (Throwable $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'code' => (string)$e->getCode(),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    exit(1);
}
"""


_VALID_IDENT_RE = re.compile(r"^[A-Za-z_][A-Za-z0-9_]*$")


class DbHelper:
    """Parameterized MariaDB helper executed via PHP PDO and PHP CLI runner."""

    def __init__(
        self,
        db_config: Mapping[str, Any] | None = None,
        php_bin: str | os.PathLike[str] = PHP_BIN,
        project_root: str | os.PathLike[str] = PROJECT_ROOT,
    ) -> None:
        self.db_config: dict[str, Any] = dict(DB_CONFIG if db_config is None else db_config)
        self.php_bin: str = os.fspath(php_bin)
        self.project_root: str = os.fspath(project_root)

    def _run_pdo(
        self,
        sql: str,
        params: Sequence[Any] | Mapping[str, Any] = (),
        timeout: int = 30,
    ) -> dict[str, Any]:
        if isinstance(params, Mapping):
            params_payload: Any = dict(params)
        else:
            params_payload = list(params)

        payload = json.dumps(
            {"db": self.db_config, "sql": sql, "params": params_payload},
            ensure_ascii=False,
        ).encode("utf-8")

        proc = subprocess.run(
            [self.php_bin, "-d", "display_errors=0", "-d", "html_errors=0", "-r", _PHP_PDO_BRIDGE],
            input=payload,
            capture_output=True,
            timeout=timeout,
            shell=False,
        )
        stdout_text = proc.stdout.decode("utf-8", errors="replace").strip()
        stderr_text = proc.stderr.decode("utf-8", errors="replace").strip()
        try:
            data = json.loads(stdout_text) if stdout_text else {}
        except json.JSONDecodeError as exc:
            raise DbError(
                f"Invalid JSON from PHP PDO bridge (rc={proc.returncode}): stdout={stdout_text!r}, stderr={stderr_text!r}"
            ) from exc

        if proc.returncode != 0 or not data.get("ok"):
            code = data.get("code", str(proc.returncode))
            msg = data.get("error") or stderr_text or stdout_text or "Unknown PDO error"
            raise DbError(f"DB error [{code}]: {msg}")

        return data

    def query(self, sql: str, params: Sequence[Any] | Mapping[str, Any] = ()) -> list[dict[str, Any]]:
        res = self._run_pdo(sql, params)
        rows = res.get("rows")
        return list(rows) if rows else []

    def scalar(
        self,
        sql: str,
        params: Sequence[Any] | Mapping[str, Any] = (),
        default: Any = None,
    ) -> Any:
        rows = self.query(sql, params)
        if not rows:
            return default
        first_row = rows[0]
        if not first_row:
            return default
        return next(iter(first_row.values()))

    def execute(self, sql: str, params: Sequence[Any] | Mapping[str, Any] = ()) -> int:
        res = self._run_pdo(sql, params)
        return int(res.get("rowCount", 0))

    def insert(self, sql: str, params: Sequence[Any] | Mapping[str, Any] = ()) -> int:
        res = self._run_pdo(sql, params)
        raw_id = res.get("lastInsertId", 0)
        try:
            return int(raw_id) if raw_id not in (None, "", "0") else 0
        except (TypeError, ValueError):
            return 0

    def table_exists(self, name: str) -> bool:
        cnt = self.scalar(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
            [self.db_config["database"], name],
            default=0,
        )
        return int(cnt or 0) > 0

    def column_exists(self, table: str, col: str) -> bool:
        cnt = self.scalar(
            "SELECT COUNT(*) FROM information_schema.columns "
            "WHERE table_schema = ? AND table_name = ? AND column_name = ?",
            [self.db_config["database"], table, col],
            default=0,
        )
        return int(cnt or 0) > 0

    def table_schema(self, table: str) -> list[dict[str, Any]]:
        if not _VALID_IDENT_RE.fullmatch(table):
            raise ValueError(f"Invalid SQL table identifier: {table!r}")
        return self.query(
            "SELECT COLUMN_NAME AS `Field`, COLUMN_TYPE AS `Type`, IS_NULLABLE AS `Null`, "
            "COLUMN_KEY AS `Key`, COLUMN_DEFAULT AS `Default`, EXTRA AS `Extra` "
            "FROM information_schema.columns "
            "WHERE table_schema = ? AND table_name = ? "
            "ORDER BY ORDINAL_POSITION",
            [self.db_config["database"], table],
        )

    def column_type(self, table: str, col: str) -> str | None:
        val = self.scalar(
            "SELECT COLUMN_TYPE FROM information_schema.columns "
            "WHERE table_schema = ? AND table_name = ? AND column_name = ?",
            [self.db_config["database"], table, col],
            default=None,
        )
        return None if val is None else str(val)

    def run_php_cli(
        self,
        rel_path: str,
        args: Sequence[str] = (),
        env: Mapping[str, str] | None = None,
        timeout: int = 30,
    ) -> CliResult:
        root_real = os.path.realpath(self.project_root)
        target = os.path.realpath(os.path.join(root_real, rel_path))
        if not target.startswith(root_real + os.sep):
            raise ValueError(f"Path escapes project root: {rel_path!r}")
        if not os.path.isfile(target):
            raise FileNotFoundError(f"PHP CLI script not found: {target}")

        merged_env = os.environ.copy()
        if env:
            for k, v in env.items():
                merged_env[str(k)] = str(v)

        proc = subprocess.run(
            [self.php_bin, target, *[str(a) for a in args]],
            cwd=root_real,
            env=merged_env,
            capture_output=True,
            timeout=timeout,
            shell=False,
        )
        return CliResult(
            returncode=proc.returncode,
            stdout=proc.stdout.decode("utf-8", errors="replace"),
            stderr=proc.stderr.decode("utf-8", errors="replace"),
        )

    def reset_rate_limits_or_test_state(self, run_prefix: str | None = None) -> None:
        if run_prefix:
            like_pat = f"%{run_prefix}%"
            if self.table_exists("audit_logs"):
                self.execute(
                    "DELETE FROM audit_logs WHERE description LIKE ? OR metadata LIKE ?",
                    [like_pat, like_pat],
                )
            if self.table_exists("activity_logs"):
                self.execute(
                    "DELETE FROM activity_logs WHERE summary LIKE ?",
                    [like_pat],
                )

        for tbl in ("login_attempts", "rate_limits"):
            if not self.table_exists(tbl):
                continue
            if run_prefix:
                cols = {row["Field"] for row in self.table_schema(tbl)}
                for candidate in ("email", "identifier", "key_name", "rate_key"):
                    if candidate in cols:
                        self.execute(f"DELETE FROM `{tbl}` WHERE `{candidate}` LIKE ?", [f"%{run_prefix}%"])
                        break
            else:
                self.execute(f"DELETE FROM `{tbl}`")
