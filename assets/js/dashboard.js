/* EduShare Dashboard JS */
(function () {
    'use strict';

    /* ---- Confirm Delete Modal ---- */
    var deleteModal   = document.getElementById('deleteModal');
    var deleteForm    = document.getElementById('deleteForm');
    var deleteMsgEl   = document.getElementById('deleteItemName');
    var cancelDeleteBtn = document.getElementById('cancelDelete');

    document.querySelectorAll('[data-delete-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id    = btn.getAttribute('data-delete-id');
            var name  = btn.getAttribute('data-delete-name') || 'materi ini';
            var action = btn.getAttribute('data-delete-action') || '';

            if (deleteMsgEl) deleteMsgEl.textContent = name;
            if (deleteForm)  deleteForm.action = action;
            if (deleteModal) deleteModal.classList.add('open');
        });
    });

    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', function () {
            if (deleteModal) deleteModal.classList.remove('open');
        });
    }

    if (deleteModal) {
        deleteModal.addEventListener('click', function (e) {
            if (e.target === deleteModal) deleteModal.classList.remove('open');
        });
    }

    /* ---- File Drop Zone ---- */
    document.querySelectorAll('.file-drop-zone').forEach(function (zone) {
        var input  = zone.querySelector('input[type=file]');
        var nameEl = zone.querySelector('.file-selected-name');
        var label  = zone.querySelector('.file-drop-label');

        if (!input) return;

        zone.addEventListener('click', function () { input.click(); });

        input.addEventListener('change', function () {
            if (input.files && input.files[0]) {
                var fname = input.files[0].name;
                if (nameEl) { nameEl.textContent = fname; nameEl.style.display = 'block'; }
                if (label)  { label.style.display = 'none'; }
            }
        });

        zone.addEventListener('dragover', function (e) {
            e.preventDefault(); zone.classList.add('dragover');
        });
        zone.addEventListener('dragleave', function () {
            zone.classList.remove('dragover');
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                input.dispatchEvent(new Event('change'));
            }
        });
    });

    /* ---- Expandable Rejection Rows ---- */
    document.querySelectorAll('[data-toggle-rejection]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-toggle-rejection');
            var row = document.getElementById(targetId);
            if (!row) return;
            var isHidden = row.style.display === 'none' || row.style.display === '';
            row.style.display = isHidden ? 'table-row' : 'none';
            btn.textContent = isHidden ? 'Sembunyikan' : 'Lihat alasan';
        });
    });

    /* ---- Password Strength (simple) ---- */
    var newPwdInput = document.getElementById('new_password');
    var strengthBar = document.getElementById('pwdStrengthBar');
    var strengthTxt = document.getElementById('pwdStrengthTxt');

    if (newPwdInput && strengthBar) {
        newPwdInput.addEventListener('input', function () {
            var val = newPwdInput.value;
            var strength = 0;
            if (val.length >= 8) strength++;
            if (/[A-Z]/.test(val)) strength++;
            if (/[0-9]/.test(val)) strength++;
            if (/[^A-Za-z0-9]/.test(val)) strength++;

            var colors = ['#ef4444', '#f59e0b', '#3b82f6', '#22c55e'];
            var labels = ['Lemah', 'Cukup', 'Baik', 'Kuat'];
            strengthBar.style.width   = (strength * 25) + '%';
            strengthBar.style.background = colors[strength - 1] || '#ddd';
            if (strengthTxt) strengthTxt.textContent = labels[strength - 1] || '';
        });
    }

    /* ---- Confirm password ---- */
    var confirmPwd = document.getElementById('confirm_password');
    var confirmMsg = document.getElementById('confirmPwdMsg');
    if (newPwdInput && confirmPwd && confirmMsg) {
        function checkMatch() {
            if (confirmPwd.value === '') { confirmMsg.textContent = ''; return; }
            if (confirmPwd.value === newPwdInput.value) {
                confirmMsg.textContent = 'Password cocok';
                confirmMsg.style.color = '#22c55e';
            } else {
                confirmMsg.textContent = 'Password tidak cocok';
                confirmMsg.style.color = '#ef4444';
            }
        }
        confirmPwd.addEventListener('input', checkMatch);
        newPwdInput.addEventListener('input', checkMatch);
    }

    /* ---- Auto-dismiss flash messages ---- */
    document.querySelectorAll('.flash-box').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 5000);
    });

    /* ---- Mobile sidebar toggle ---- */
    var sidebarToggleBtn = document.getElementById('sidebarToggle');
    var sidebar = document.querySelector('.sidebar');
    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('mobile-open');
        });
    }

}());
