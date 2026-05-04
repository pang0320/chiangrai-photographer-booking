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
      if (button.tagName === 'BUTTON' && button.form) button.form.submit();
      if (button.tagName === 'A') window.location.href = button.href;
    }
  });
});

document.addEventListener('DOMContentLoaded', function () {
  if (window.jQuery && jQuery.fn.DataTable) {
    jQuery('.datatable').DataTable({ pageLength: 25, order: [] });
  }
});

