document.addEventListener('click', function (event) {
  const button = event.target.closest('[data-confirm]');
  if (!button) return;
  event.preventDefault();
  Swal.fire({
    icon: 'warning',
    title: button.dataset.confirm || 'ยืนยันการทำรายการ?',
    text: button.dataset.confirmText || '',
    showCancelButton: true,
    confirmButtonText: button.dataset.confirmButton || 'ยืนยัน',
    cancelButtonText: button.dataset.cancelButton || 'ยกเลิก',
    confirmButtonColor: '#e21b2d',
    cancelButtonColor: '#334155'
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

  initBlockPagination();

  if (window.jQuery && jQuery.fn.DataTable) {
    jQuery('.datatable').DataTable({
      pageLength: 5,
      lengthMenu: [[5, 10, 25, 50, 100], [5, 10, 25, 50, 100]],
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

function initBlockPagination() {
  document.querySelectorAll('[data-block-paginate]').forEach(function (block) {
    const perPage = Math.max(1, parseInt(block.dataset.blockPaginate || '5', 10));
    const itemSelector = block.dataset.blockItemSelector || '';
    const items = itemSelector ? Array.from(block.querySelectorAll(itemSelector)) : Array.from(block.children);

    if (items.length <= perPage) {
      return;
    }

    let currentPage = 1;
    const totalPages = Math.ceil(items.length / perPage);
    const control = document.createElement('div');
    control.className = 'mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-neutral-100 pt-4';

    const summary = document.createElement('div');
    summary.className = 'text-xs font-black text-neutral-500';

    const buttons = document.createElement('div');
    buttons.className = 'flex flex-wrap gap-2';

    control.appendChild(summary);
    control.appendChild(buttons);

    const insertAfter = block.tagName === 'TBODY' && block.closest('table') ? block.closest('table') : block;
    insertAfter.insertAdjacentElement('afterend', control);

    function itemDisplayValue(item) {
      if (item.tagName === 'TR') {
        return 'table-row';
      }

      if (item.tagName === 'LI') {
        return 'list-item';
      }

      return '';
    }

    function buildPageButton(page, label, iconClass, isActive, isDisabled) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = isActive
        ? 'rounded-full bg-neutral-950 px-3 py-2 text-xs font-black text-white shadow-sm'
        : 'rounded-full border border-neutral-200 bg-white px-3 py-2 text-xs font-black text-neutral-700 transition hover:bg-red-600 hover:text-white';

      if (isDisabled) {
        button.disabled = true;
        button.className = 'rounded-full border border-neutral-100 bg-neutral-50 px-3 py-2 text-xs font-black text-neutral-300';
      }

      if (iconClass) {
        button.innerHTML = '<i class="fa-solid ' + iconClass + '"></i>';
        button.setAttribute('aria-label', label);
      } else {
        button.textContent = label;
      }

      button.addEventListener('click', function () {
        if (isDisabled) return;
        currentPage = page;
        render();
      });

      return button;
    }

    function render() {
      const startIndex = (currentPage - 1) * perPage;
      const endIndex = startIndex + perPage;

      items.forEach(function (item, index) {
        if (index >= startIndex && index < endIndex) {
          item.style.display = itemDisplayValue(item);
        } else {
          item.style.display = 'none';
        }
      });

      summary.textContent = 'แสดง ' + (startIndex + 1) + '-' + Math.min(endIndex, items.length) + ' จาก ' + items.length + ' รายการ';
      buttons.innerHTML = '';
      buttons.appendChild(buildPageButton(Math.max(1, currentPage - 1), 'ก่อนหน้า', 'fa-chevron-left', false, currentPage === 1));

      for (let page = 1; page <= totalPages; page++) {
        buttons.appendChild(buildPageButton(page, String(page), '', page === currentPage, false));
      }

      buttons.appendChild(buildPageButton(Math.min(totalPages, currentPage + 1), 'ถัดไป', 'fa-chevron-right', false, currentPage === totalPages));
    }

    render();
  });
}

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
