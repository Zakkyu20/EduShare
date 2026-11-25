document.addEventListener('DOMContentLoaded', () => {
  const menu = document.querySelector('.mobile-menu');
  const nav = document.querySelector('.main-nav');
  if (menu && nav) menu.addEventListener('click', () => nav.classList.toggle('open'));
  document.querySelectorAll('input[type=file]').forEach(input => {
    input.addEventListener('change', () => {
      const label = input.closest('.file-drop');
      if (label && input.files.length) label.classList.add('has-file');
    });
  });
});
