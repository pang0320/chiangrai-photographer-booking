document.addEventListener('click', function (event) {
  const button = event.target.closest('[data-confirm]');
  if (!button) return;
  event.preventDefault();
  Swal.fire({
    icon: 'warning',
    title: button.dataset.confirm || 'ยืนยันการทำรายการ?',
    showCancelButton: true,
    confirmButtonText: 'ยืนยัน',
    cancelButtonText: 'ยกเลิก'
  }).then((result) => {
    if (result.isConfirmed) {
      if (button.tagName === 'BUTTON' && button.form) {
        if (button.name) {
          const hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = button.name;
          hidden.value = button.value;
          button.form.appendChild(hidden);
        }

        const loader = document.getElementById('page-loader');
        if (loader) loader.classList.remove('hidden');
        button.form.submit();
      }
      if (button.tagName === 'A') window.location.href = button.href;
    }
  });
});

document.addEventListener('DOMContentLoaded', function () {
  if (window.jQuery && jQuery.fn.DataTable) {
    jQuery('.datatable').DataTable({ pageLength: 25, order: [] });
  }
});

document.addEventListener('submit', function () {
  const loader = document.getElementById('page-loader');
  if (loader) loader.classList.remove('hidden');
});
