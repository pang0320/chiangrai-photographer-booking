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

        syncAllBeDateInputs();
        const loader = document.getElementById('page-loader');
        if (loader) loader.classList.remove('hidden');
        button.form.submit();
      }
      if (button.tagName === 'A') window.location.href = button.href;
    }
  });
});

document.addEventListener('DOMContentLoaded', function () {
  const developerModal = document.getElementById('developer-modal');
  const developerOpenButtons = document.querySelectorAll('[data-developer-modal-open]');
  const developerCloseButtons = document.querySelectorAll('[data-developer-modal-close], [data-developer-modal-backdrop]');

  function openDeveloperModal() {
    if (!developerModal) return;
    developerModal.classList.remove('hidden');
    developerModal.classList.add('flex');
    developerModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
  }

  function closeDeveloperModal() {
    if (!developerModal) return;
    developerModal.classList.add('hidden');
    developerModal.classList.remove('flex');
    developerModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('overflow-hidden');
  }

  developerOpenButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      openDeveloperModal();
    });
  });

  developerCloseButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      closeDeveloperModal();
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeDeveloperModal();
    }
  });

  if (window.jQuery && jQuery.fn.DataTable) {
    jQuery('.datatable').DataTable({
      pageLength: 10,
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      order: [],
      language: {
        search: 'ค้นหา:',
        lengthMenu: 'แสดง _MENU_ รายการ',
        info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
        infoEmpty: 'ไม่มีข้อมูล',
        infoFiltered: '(กรองจากทั้งหมด _MAX_ รายการ)',
        zeroRecords: 'ไม่พบข้อมูล',
        paginate: {
          first: 'แรก',
          last: 'สุดท้าย',
          next: 'ถัดไป',
          previous: 'ก่อนหน้า'
        }
      }
    });
  }

  document.querySelectorAll('[data-be-date-visible]').forEach(function (input) {
    const hidden = document.getElementById(input.dataset.target || '');
    if (!hidden) return;

    const syncDate = function (shouldFormat) {
      const isoDate = parseBeDateToIso(input.value);
      hidden.value = isoDate;

      if (shouldFormat && isoDate) {
        input.value = formatIsoToBeDate(isoDate);
      }
    };

    input.addEventListener('input', function () {
      syncDate(false);
    });

    input.addEventListener('blur', function () {
      syncDate(true);
    });

    if (hidden.value && !input.value) {
      input.value = formatIsoToBeDate(hidden.value);
    }
  });
});

document.addEventListener('submit', function () {
  syncAllBeDateInputs();

  const loader = document.getElementById('page-loader');
  if (loader) loader.classList.remove('hidden');
});

function syncAllBeDateInputs() {
  document.querySelectorAll('[data-be-date-visible]').forEach(function (input) {
    const hidden = document.getElementById(input.dataset.target || '');
    if (!hidden) return;
    hidden.value = parseBeDateToIso(input.value);
  });
}

function parseBeDateToIso(value) {
  const raw = String(value || '').trim();
  let match;
  let year;
  let month;
  let day;

  if (raw === '') {
    return '';
  }

  match = raw.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  if (match) {
    year = parseInt(match[1], 10);
    month = parseInt(match[2], 10);
    day = parseInt(match[3], 10);

    if (year >= 2400) {
      year -= 543;
    }

    if (isValidDate(year, month, day)) {
      return buildIsoDate(year, month, day);
    }
  }

  match = raw.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/);
  if (match) {
    day = parseInt(match[1], 10);
    month = parseInt(match[2], 10);
    year = parseInt(match[3], 10);

    if (year >= 2400) {
      year -= 543;
    }

    if (isValidDate(year, month, day)) {
      return buildIsoDate(year, month, day);
    }
  }

  match = raw.match(/^(\d{4})[\/.](\d{1,2})[\/.](\d{1,2})$/);
  if (match) {
    year = parseInt(match[1], 10);
    month = parseInt(match[2], 10);
    day = parseInt(match[3], 10);

    if (year >= 2400) {
      year -= 543;
    }

    if (isValidDate(year, month, day)) {
      return buildIsoDate(year, month, day);
    }
  }

  return '';
}

function formatIsoToBeDate(value) {
  const isoDate = parseBeDateToIso(value);
  const match = isoDate.match(/^(\d{4})-(\d{2})-(\d{2})$/);

  if (!match) {
    return '';
  }

  return match[3] + '/' + match[2] + '/' + (parseInt(match[1], 10) + 543);
}

function isValidDate(year, month, day) {
  const date = new Date(year, month - 1, day);

  return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
}

function buildIsoDate(year, month, day) {
  return String(year).padStart(4, '0') + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
}
