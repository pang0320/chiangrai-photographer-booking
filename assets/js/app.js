/**
 * ซ่อนตัวโหลดหน้าเว็บ (Page Loader)
 */
function hidePageLoader() {
  const loader = document.getElementById('page-loader');
  if (loader) loader.classList.add('hidden');
}

window.addEventListener('pageshow', hidePageLoader);

document.addEventListener('visibilitychange', function () {
  if (!document.hidden) {
    hidePageLoader();
  }
});

document.addEventListener('click', function (event) {
  const alertButton = event.target.closest('[data-alert]');
  if (alertButton) {
    event.preventDefault();
    Swal.fire({
      icon: alertButton.dataset.alertIcon || 'info',
      title: alertButton.dataset.alertTitle || alertButton.dataset.alert || 'แจ้งเตือน',
      text: alertButton.dataset.alertText || '',
      confirmButtonText: alertButton.dataset.alertButton || 'เข้าใจแล้ว',
      confirmButtonColor: '#e21b2d'
    });
    return;
  }

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

// Close all <details> dropdowns when clicking outside
document.addEventListener('click', function (event) {
  document.querySelectorAll('details[open]').forEach(function (details) {
    if (!details.contains(event.target)) {
      details.removeAttribute('open');
    }
  });
});

document.addEventListener('DOMContentLoaded', function () {
  hidePageLoader();

  const developerModal = document.getElementById('developer-modal');
  const developerOpenButtons = document.querySelectorAll('[data-developer-modal-open]');
  const developerCloseButtons = document.querySelectorAll('[data-developer-modal-close], [data-developer-modal-backdrop]');

  /**
   * เปิด Modal สำหรับนักพัฒนา
   */
  function openDeveloperModal() {
    if (!developerModal) return;
    developerModal.classList.remove('hidden');
    developerModal.classList.add('flex');
    developerModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
  }

  /**
   * ปิด Modal สำหรับนักพัฒนา
   */
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
  initCalendarDateInputs();
  initClickableCards();

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

    /**
     * ซิงค์วันที่จากอินพุตแสดงผลไปยังอินพุตที่ซ่อนอยู่ (Hidden Input) ในรูปแบบ ISO
     * @param {boolean} shouldFormat กำหนดว่าต้องจัดรูปแบบวันที่ใหม่หรือไม่
     */
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

  const backToTopButton = document.getElementById('back-to-top');
  if (backToTopButton) {
    window.addEventListener('scroll', function () {
      if (window.scrollY > window.innerHeight) {
        backToTopButton.classList.remove('invisible', 'opacity-0', 'translate-y-4');
        backToTopButton.classList.add('opacity-100', 'translate-y-0');
      } else {
        backToTopButton.classList.add('invisible', 'opacity-0', 'translate-y-4');
        backToTopButton.classList.remove('opacity-100', 'translate-y-0');
      }
    });

    backToTopButton.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }
});

/**
 * เริ่มการทำงานของการ์ดที่คลิกได้ (Clickable Card)
 */
function initClickableCards() {
  const interactiveSelector = 'a, button, input, select, textarea, label, form, [data-card-ignore]';

  document.querySelectorAll('[data-clickable-card]').forEach(function (card) {
    /**
     * จัดการเหตุการณ์เมื่อคลิกที่การ์ด
     * @param {Event} event เหตุการณ์การคลิก
     */
    function openCard(event) {
      if (event && event.defaultPrevented) return;

      const target = event ? event.target : null;
      if (target && target.closest(interactiveSelector)) {
        return;
      }

      const formId = card.dataset.cardForm || '';
      const form = formId ? document.getElementById(formId) : null;
      if (!form) return;

      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    }

    card.addEventListener('click', openCard);
    card.addEventListener('keydown', function (event) {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }

      event.preventDefault();
      openCard(null);
    });
  });
}

/**
 * เริ่มการทำงานของอินพุตวันที่แบบปฏิทิน
 */
function initCalendarDateInputs() {
  const thaiMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

  document.querySelectorAll('[data-calendar-date]').forEach(function (calendar) {
    const hidden = document.getElementById(calendar.dataset.target || '');
    const grid = calendar.querySelector('[data-calendar-grid]');
    const monthLabel = calendar.querySelector('[data-calendar-month]');
    const selectedLabel = calendar.querySelector('[data-calendar-selected]');
    const selectedPopoverLabel = calendar.querySelector('[data-calendar-selected-popover]');
    const trigger = calendar.querySelector('[data-calendar-trigger]');
    const popover = calendar.querySelector('[data-calendar-popover]');
    const prevButton = calendar.querySelector('[data-calendar-prev]');
    const nextButton = calendar.querySelector('[data-calendar-next]');
    const today = new Date();
    const minMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    let statuses = {};
    let selectableStatuses = [];
    let current;

    if (!hidden || !grid || !monthLabel) return;

    try {
      statuses = JSON.parse(calendar.dataset.statuses || '{}') || {};
    } catch (error) {
      statuses = {};
    }
    try {
      selectableStatuses = JSON.parse(calendar.dataset.selectableStatuses || '[]') || [];
    } catch (error) {
      selectableStatuses = [];
    }

    const selectedIso = parseBeDateToIso(hidden.value);
    if (selectedIso) {
      const parts = selectedIso.split('-').map(function (part) { return parseInt(part, 10); });
      current = new Date(parts[0], parts[1] - 1, 1);
      if (current < minMonth) {
        current = new Date(minMonth.getFullYear(), minMonth.getMonth(), 1);
      }
    } else {
      current = new Date(today.getFullYear(), today.getMonth(), 1);
    }

    /**
     * วาดตารางปฏิทินตามเดือนและปีที่เลือก
     */
    function renderCalendar() {
      const year = current.getFullYear();
      const month = current.getMonth();
      const firstDay = new Date(year, month, 1).getDay();
      const daysInMonth = new Date(year, month + 1, 0).getDate();
      const selected = parseBeDateToIso(hidden.value);
      const todayIso = buildIsoDate(today.getFullYear(), today.getMonth() + 1, today.getDate());

      monthLabel.textContent = thaiMonths[month] + ' ' + (year + 543);
      grid.innerHTML = '';

      if (prevButton) {
        const isMinMonth = current.getFullYear() === minMonth.getFullYear() && current.getMonth() === minMonth.getMonth();
        prevButton.disabled = isMinMonth;
        prevButton.classList.toggle('calendar-nav-disabled', isMinMonth);
        prevButton.setAttribute('aria-disabled', isMinMonth ? 'true' : 'false');
      }

      for (let blank = 0; blank < firstDay; blank++) {
        const spacer = document.createElement('span');
        spacer.className = 'calendar-day calendar-day-empty';
        grid.appendChild(spacer);
      }

      for (let day = 1; day <= daysInMonth; day++) {
        const iso = buildIsoDate(year, month + 1, day);
        const status = statuses[iso] || calendar.dataset.defaultStatus || 'available';
        const isPast = iso < todayIso;
        const isSelectableStatus = selectableStatuses.length === 0 || selectableStatuses.indexOf(status) !== -1;
        const isDisabled = isPast || !isSelectableStatus;
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = String(day);
        button.className = 'calendar-day calendar-day-' + status;
        button.dataset.date = iso;
        button.disabled = isDisabled;
        if (isPast) {
          button.className += ' calendar-day-past';
        }
        if (isDisabled) {
          button.className += ' calendar-day-disabled';
        }

        if (iso === selected) {
          button.className += ' calendar-day-selected';
        }

        button.addEventListener('click', function () {
          if (isDisabled) return;
          hidden.value = iso;
          if (selectedLabel) {
            selectedLabel.textContent = formatIsoToBeDate(iso);
          }
          if (selectedPopoverLabel) {
            selectedPopoverLabel.textContent = formatIsoToBeDate(iso);
          }
          closeCalendar();
          renderCalendar();
          hidden.dispatchEvent(new Event('change', { bubbles: true }));
        });

        grid.appendChild(button);
      }
    }

    if (prevButton) {
      prevButton.addEventListener('click', function () {
        const previousMonth = new Date(current.getFullYear(), current.getMonth() - 1, 1);
        if (previousMonth < minMonth) {
          return;
        }
        current = previousMonth;
        renderCalendar();
      });
    }

    if (nextButton) {
      nextButton.addEventListener('click', function () {
        current = new Date(current.getFullYear(), current.getMonth() + 1, 1);
        renderCalendar();
      });
    }

    /**
     * เปิด Popover ปฏิทิน
     */
    function openCalendar() {
      document.querySelectorAll('[data-calendar-date].calendar-date-open').forEach(function (item) {
        if (item !== calendar) {
          item.classList.remove('calendar-date-open');
        }
      });
      calendar.classList.add('calendar-date-open');
    }

    /**
     * ปิด Popover ปฏิทิน
     */
    function closeCalendar() {
      calendar.classList.remove('calendar-date-open');
    }

    if (trigger && popover) {
      trigger.addEventListener('click', function (event) {
        event.stopPropagation();
        if (calendar.classList.contains('calendar-date-open')) {
          closeCalendar();
        } else {
          openCalendar();
        }
      });

      popover.addEventListener('click', function (event) {
        event.stopPropagation();
      });
    }

    renderCalendar();
  });
}

document.addEventListener('click', function () {
  document.querySelectorAll('[data-calendar-date].calendar-date-open').forEach(function (calendar) {
    calendar.classList.remove('calendar-date-open');
  });
});

document.addEventListener('keydown', function (event) {
  if (event.key !== 'Escape') return;
  document.querySelectorAll('[data-calendar-date].calendar-date-open').forEach(function (calendar) {
    calendar.classList.remove('calendar-date-open');
  });
});

/**
 * เริ่มการทำงานของการแบ่งหน้าแบบบล็อก (Block Pagination)
 */
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

    /**
     * คืนค่าประเภทการแสดงผล (CSS display) ตามชนิดของแท็ก HTML
     * @param {HTMLElement} item องค์ประกอบ HTML
     * @returns {string} ค่า CSS display
     */
    function itemDisplayValue(item) {
      if (item.tagName === 'TR') {
        return 'table-row';
      }

      if (item.tagName === 'LI') {
        return 'list-item';
      }

      return '';
    }

    /**
     * สร้างปุ่มแบ่งหน้า
     * @param {number} page หมายเลขหน้า
     * @param {string} label ข้อความบนปุ่ม
     * @param {string} iconClass คลาสของไอคอน FontAwesome
     * @param {boolean} isActive สถานะว่าปุ่มนี้คือหน้าปัจจุบันหรือไม่
     * @param {boolean} isDisabled สถานะว่าปุ่มนี้ปิดการใช้งานหรือไม่
     * @returns {HTMLButtonElement} ปุ่มแบ่งหน้า
     */
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

    /**
     * แสดงผลรายการในหน้าปัจจุบันและอัปเดตปุ่มควบคุม
     */
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

/**
 * ซิงค์ค่าของอินพุตวันที่พุทธศักราชทุกตัวไปยังอินพุตที่ซ่อนอยู่
 */
function syncAllBeDateInputs() {
  document.querySelectorAll('[data-be-date-visible]').forEach(function (input) {
    const hidden = document.getElementById(input.dataset.target || '');
    if (!hidden) return;
    hidden.value = parseBeDateToIso(input.value);
  });
}

/**
 * แปลงวันที่ในรูปแบบพุทธศักราชให้เป็น ISO (คริสต์ศักราช YYYY-MM-DD)
 * @param {string} value วันที่ในรูปแบบต่างๆ เช่น 05/05/2569 หรือ 2026-05-05
 * @returns {string} วันที่ในรูปแบบ ISO
 */
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

/**
 * แปลงวันที่ ISO ให้เป็นรูปแบบพุทธศักราชสำหรับการแสดงผล (DD/MM/BE)
 * @param {string} value วันที่ ISO
 * @returns {string} วันที่รูปแบบพุทธศักราช
 */
function formatIsoToBeDate(value) {
  const isoDate = parseBeDateToIso(value);
  const match = isoDate.match(/^(\d{4})-(\d{2})-(\d{2})$/);

  if (!match) {
    return '';
  }

  return match[3] + '/' + match[2] + '/' + (parseInt(match[1], 10) + 543);
}

/**
 * ตรวจสอบความถูกต้องของวันที่
 * @param {number} year ปี (ค.ศ.)
 * @param {number} month เดือน (1-12)
 * @param {number} day วันที่
 * @returns {boolean} ผลการตรวจสอบ
 */
function isValidDate(year, month, day) {
  const date = new Date(year, month - 1, day);

  return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
}

/**
 * สร้างข้อความวันที่ในรูปแบบ ISO (YYYY-MM-DD)
 * @param {number} year ปี
 * @param {number} month เดือน
 * @param {number} day วัน
 * @returns {string} วันที่ในรูปแบบ ISO
 */
function buildIsoDate(year, month, day) {
  return String(year).padStart(4, '0') + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
}
