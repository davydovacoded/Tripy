'use strict';

const formatSingleDate = (value) => value
    ? new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${value}T12:00:00`))
    : '';

const initializeSingleDatePickers = (scope = document) => {
    const singleDatePickers = window.TripySingleDatePickers || new Map();

    scope.querySelectorAll('[data-single-date-picker]').forEach((pickerRoot) => {
        if (pickerRoot.dataset.singleDateReady === 'true') return;

        const input = pickerRoot.querySelector('input[type="hidden"]');
        const control = pickerRoot.querySelector('[data-single-date-control]');
        const valueText = pickerRoot.querySelector('[data-single-date-value]');
        const popover = pickerRoot.querySelector('[data-single-date-popover]');
        const title = pickerRoot.querySelector('[data-single-date-title]');
        const days = pickerRoot.querySelector('[data-single-date-days]');
        const prev = pickerRoot.querySelector('[data-single-date-prev]');
        const next = pickerRoot.querySelector('[data-single-date-next]');

        if (!input || !control || !valueText || !popover || !title || !days || !prev || !next) return;

        pickerRoot.dataset.singleDateReady = 'true';
        const emptyLabel = valueText.textContent;
        const months = ['январь','февраль','март','апрель','май','июнь','июль','август','сентябрь','октябрь','ноябрь','декабрь'];
        let selected = input.value || '';
        let view = selected ? new Date(`${selected}T12:00:00`) : new Date();
        const iso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        const sync = () => { input.value = selected; valueText.textContent = selected ? formatSingleDate(selected) : emptyLabel; control.classList.toggle('has-value', Boolean(selected)); };
        const render = () => {
            title.textContent = `${months[view.getMonth()]} ${view.getFullYear()}`;
            const first = new Date(view.getFullYear(), view.getMonth(), 1);
            const offset = (first.getDay() + 6) % 7;
            const total = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
            const cells = Array.from({ length: offset }, () => '<span></span>');
            for (let day = 1; day <= total; day += 1) {
                const date = iso(new Date(view.getFullYear(), view.getMonth(), day));
                cells.push(`<button type="button" data-single-date="${date}" class="${date === selected ? 'is-edge' : ''}" aria-pressed="${date === selected}">${day}</button>`);
            }
            days.innerHTML = cells.join('');
        };
        const close = () => { popover.hidden = true; control.setAttribute('aria-expanded', 'false'); };
        control.addEventListener('click', () => { popover.hidden = !popover.hidden; control.setAttribute('aria-expanded', String(!popover.hidden)); if (!popover.hidden) render(); });
        prev.addEventListener('click', () => { view = new Date(view.getFullYear(), view.getMonth() - 1, 1); render(); });
        next.addEventListener('click', () => { view = new Date(view.getFullYear(), view.getMonth() + 1, 1); render(); });
        days.addEventListener('click', (event) => { const button = event.target.closest('[data-single-date]'); if (!button) return; selected = button.dataset.singleDate; sync(); render(); window.setTimeout(close, 120); });
        const setDate = (date = '') => { selected = date || ''; view = selected ? new Date(`${selected}T12:00:00`) : new Date(); sync(); render(); close(); };
        singleDatePickers.set(input.id, { setDate });
        sync();
    });

    window.TripySingleDatePickers = singleDatePickers;
    return singleDatePickers;
};

window.TripyInitializeSingleDatePickers = initializeSingleDatePickers;

document.addEventListener('DOMContentLoaded', () => {
    const SIDEBAR_STORAGE_KEY = 'tripy-sidebar-expanded';
    const body = document.body;
    const sidebarToggle = document.querySelector('#sidebar-toggle');
    const sidebarBackdrop = document.querySelector('#sidebar-backdrop');
    const projectInfoDialog = document.querySelector('#project-info-dialog');
    const filterButtons = [...document.querySelectorAll('.filter-tab')];
    const tripGrid = document.querySelector('#trip-grid');
    const tripCards = [...document.querySelectorAll('.trip-ticket')];
    const emptyState = document.querySelector('#empty-state');
    const sortSelect = document.querySelector('#trip-sort');
    const tripFormDialog = document.querySelector('#trip-form-dialog');

    const isCompactViewport = () => window.matchMedia('(max-width: 840px)').matches;

    const setSidebarState = (isExpanded, savePreference = true) => {
        body.classList.toggle('sidebar-expanded', isExpanded);
        sidebarToggle?.setAttribute('aria-expanded', String(isExpanded));
        sidebarToggle?.setAttribute('aria-label', isExpanded ? 'Свернуть боковое меню' : 'Раскрыть боковое меню');

        if (sidebarBackdrop) {
            sidebarBackdrop.hidden = !(isExpanded && isCompactViewport());
        }

        if (savePreference) {
            try {
                localStorage.setItem(SIDEBAR_STORAGE_KEY, String(isExpanded));
            } catch (error) {}
        }
    };

    let savedSidebarState = null;
    try {
        savedSidebarState = localStorage.getItem(SIDEBAR_STORAGE_KEY);
    } catch (error) {}
    setSidebarState(savedSidebarState === 'true', false);

    sidebarToggle?.addEventListener('click', () => {
        setSidebarState(!body.classList.contains('sidebar-expanded'));
    });

    sidebarBackdrop?.addEventListener('click', () => setSidebarState(false));

    window.addEventListener('resize', () => {
        if (sidebarBackdrop) {
            sidebarBackdrop.hidden = !(body.classList.contains('sidebar-expanded') && isCompactViewport());
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isCompactViewport() && body.classList.contains('sidebar-expanded')) {
            setSidebarState(false);
            sidebarToggle?.focus();
        }
    });

    const closeCardMenus = (exception = null) => {
        document.querySelectorAll('.card-menu').forEach((menu) => {
            if (menu !== exception) {
                menu.hidden = true;
                menu.previousElementSibling?.setAttribute('aria-expanded', 'false');
            }
        });
    };

    document.querySelector('#project-info-open')?.addEventListener('click', () => {
        if (projectInfoDialog && typeof projectInfoDialog.showModal === 'function') projectInfoDialog.showModal();
    });
    projectInfoDialog?.querySelector('.project-info-dialog__close')?.addEventListener('click', () => projectInfoDialog.close());
    projectInfoDialog?.addEventListener('click', (event) => {
        if (event.target === projectInfoDialog) projectInfoDialog.close();
    });

    document.querySelectorAll('[data-photo-option-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const option = toggle.closest('[data-photo-option]');
            const content = option?.querySelector('.trip-option__content');
            if (!content) return;
            const isOpen = content.hidden;
            content.hidden = !isOpen;
            option.classList.toggle('is-open', isOpen);
            toggle.setAttribute('aria-expanded', String(isOpen));
        });
    });

    initializeSingleDatePickers();

    const setupTripForm = () => {
        if (!tripFormDialog) return;
        const form = tripFormDialog.querySelector('[data-trip-form]');
        const startInput = form.elements.start_date;
        const endInput = form.elements.end_date;
        const rangeControl = form.querySelector('.date-range-field__control');
        const rangeValue = form.querySelector('[data-date-range-value]');
        const picker = form.querySelector('#trip-date-picker');
        const daysGrid = form.querySelector('[data-calendar-days]');
        const calendarTitle = form.querySelector('[data-calendar-title]');
        const calendarHint = form.querySelector('[data-calendar-hint]');
        const coverInput = form.elements.cover;
        const coverPreview = form.querySelector('[data-cover-preview]');
        const coverError = form.querySelector('[data-cover-error]');
        const months = ['январь','февраль','март','апрель','май','июнь','июль','август','сентябрь','октябрь','ноябрь','декабрь'];
        let selectedStart = startInput.value || '';
        let selectedEnd = endInput.value || '';
        let viewDate = selectedStart ? new Date(`${selectedStart}T12:00:00`) : new Date();
        let initialState = '';
        let submitting = false;

        const iso = (date) => `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
        const formatRange = () => {
            if (!selectedStart || !selectedEnd) return 'Даты путешествия';
            const start = new Date(`${selectedStart}T12:00:00`), end = new Date(`${selectedEnd}T12:00:00`);
            const monthCases = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
            const monthName = (date) => monthCases[date.getMonth()];
            if (selectedStart === selectedEnd) return `${start.getDate()} ${monthName(start)} ${start.getFullYear()}`;
            if (start.getFullYear() === end.getFullYear() && start.getMonth() === end.getMonth()) return `${start.getDate()}–${end.getDate()} ${monthName(end)} ${end.getFullYear()}`;
            return `${start.getDate()} ${monthName(start)} ${start.getFullYear()} – ${end.getDate()} ${monthName(end)} ${end.getFullYear()}`;
        };
        const syncRange = () => {
            startInput.value = selectedStart;
            endInput.value = selectedEnd;
            rangeValue.textContent = formatRange();
            rangeControl.classList.toggle('has-value', Boolean(selectedStart && selectedEnd));
        };
        const renderCalendar = () => {
            calendarTitle.textContent = `${months[viewDate.getMonth()]} ${viewDate.getFullYear()}`;
            const first = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
            const offset = (first.getDay() + 6) % 7;
            const total = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
            const cells = [];
            for (let i=0;i<offset;i+=1) cells.push('<span></span>');
            for (let day=1;day<=total;day+=1) {
                const value = iso(new Date(viewDate.getFullYear(), viewDate.getMonth(), day));
                const edge = value===selectedStart || value===selectedEnd;
                const between = selectedStart && selectedEnd && value>selectedStart && value<selectedEnd;
                cells.push(`<button type="button" data-calendar-date="${value}" class="${edge?'is-edge ':''}${between?'is-between':''}" aria-pressed="${edge||between}">${day}</button>`);
            }
            daysGrid.innerHTML = cells.join('');
            calendarHint.textContent = selectedStart && !selectedEnd ? 'Теперь выберите дату окончания' : 'Выберите дату начала, затем дату окончания';
        };
        const closePicker = () => { picker.hidden = true; rangeControl.setAttribute('aria-expanded','false'); };
        rangeControl.addEventListener('click', () => { picker.hidden = !picker.hidden; rangeControl.setAttribute('aria-expanded',String(!picker.hidden)); if(!picker.hidden)renderCalendar(); });
        form.querySelector('[data-calendar-prev]').addEventListener('click',()=>{viewDate=new Date(viewDate.getFullYear(),viewDate.getMonth()-1,1);renderCalendar();});
        form.querySelector('[data-calendar-next]').addEventListener('click',()=>{viewDate=new Date(viewDate.getFullYear(),viewDate.getMonth()+1,1);renderCalendar();});
        daysGrid.addEventListener('click',(event)=>{
            const button=event.target.closest('[data-calendar-date]'); if(!button)return;
            const value=button.dataset.calendarDate;
            if(!selectedStart || selectedEnd){selectedStart=value;selectedEnd='';}
            else if(value<selectedStart){selectedEnd=selectedStart;selectedStart=value;}
            else{selectedEnd=value;}
            syncRange(); renderCalendar();
            if(selectedEnd) window.setTimeout(closePicker,160);
        });
        form.querySelectorAll('.trip-option__toggle').forEach((toggle)=>toggle.addEventListener('click',()=>{
            const option=toggle.closest('.trip-option'), content=option.querySelector('.trip-option__content'), open=content.hidden;
            content.hidden=!open; option.classList.toggle('is-open',open); toggle.setAttribute('aria-expanded',String(open));
        }));
        coverInput.addEventListener('change',()=>{
            const file=coverInput.files[0]; coverError.hidden=true;
            if(!file)return;
            if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>8*1024*1024){coverInput.value='';coverError.textContent='Выберите JPG, PNG или WebP размером до 8 МБ.';coverError.hidden=false;return;}
            coverPreview.src=URL.createObjectURL(file);
        });
        const snapshot = () => JSON.stringify([...new FormData(form).entries()].filter(([key])=>key!=='csrf_token'&&key!=='cover'));
        const openForm = () => { syncRange(); renderCalendar(); initialState=snapshot(); tripFormDialog.showModal(); };
        const tryClose = () => {
            const changed=snapshot()!==initialState || Boolean(coverInput.files[0]);
            if(!changed || window.confirm('Закрыть форму? Несохранённые изменения будут потеряны.')) tripFormDialog.close();
        };
        document.querySelectorAll('[data-open-trip-form]').forEach((button)=>button.addEventListener('click',openForm));
        form.querySelectorAll('[data-trip-form-close]').forEach((button)=>button.addEventListener('click',tryClose));
        tripFormDialog.addEventListener('cancel',(event)=>{event.preventDefault();tryClose();});
        tripFormDialog.addEventListener('click',(event)=>{if(event.target===tripFormDialog)tryClose();});
        form.addEventListener('submit',async (event)=>{
            event.preventDefault();
            form.querySelectorAll('.has-error').forEach((field)=>field.classList.remove('has-error'));
            form.querySelectorAll('.field-error').forEach((error)=>{error.textContent='';});
            const formError=form.querySelector('[data-trip-form-error]');formError.hidden=true;
            let invalid=null;
            if(!form.elements.city.value.trim()){invalid=form.elements.city;invalid.closest('.minimal-field').classList.add('has-error');invalid.closest('.minimal-field').querySelector('.field-error').textContent='Введите город.';}
            else if(!form.elements.country.value.trim()){invalid=form.elements.country;invalid.closest('.minimal-field').classList.add('has-error');invalid.closest('.minimal-field').querySelector('.field-error').textContent='Введите страну.';}
            else if(!selectedStart||!selectedEnd){invalid=rangeControl;form.querySelector('.date-range-field').classList.add('has-error');form.querySelector('[data-date-error]').textContent='Выберите даты путешествия.';}
            if(invalid){invalid.focus();return;}
            if(submitting)return;
            submitting=true;const submit=form.querySelector('[type="submit"]');submit.disabled=true;
            try{
                const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json'},body:new FormData(form)});
                const result=await response.json().catch(()=>({ok:false,message:'Сервер вернул некорректный ответ.'}));
                if(!response.ok||!result.ok)throw new Error(result.message||'Не удалось сохранить путешествие.');
                window.location.href=result.redirect;
            }catch(error){
                formError.textContent=error.message;formError.hidden=false;submitting=false;submit.disabled=false;
            }
        });
        syncRange();
        const params=new URLSearchParams(window.location.search);
        if(params.has('create')||params.has('edit'))openForm();
    };
    setupTripForm();
    document.querySelectorAll('[data-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });
    const appFlash = document.querySelector('[data-app-flash]');
    if (appFlash) window.setTimeout(() => appFlash.remove(), 4200);

    document.querySelectorAll('.js-actions').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const menu = button.nextElementSibling;
            const willOpen = menu.hidden;
            closeCardMenus(menu);
            menu.hidden = !willOpen;
            button.setAttribute('aria-expanded', String(willOpen));
        });
    });

    document.addEventListener('click', () => closeCardMenus());

    const updateCards = (filter) => {
        let visibleCount = 0;

        tripCards.forEach((card) => {
            const shouldShow = filter === 'all' || card.dataset.status === filter;
            card.hidden = !shouldShow;
            visibleCount += shouldShow ? 1 : 0;
        });

        if (emptyState) emptyState.hidden = visibleCount !== 0;
    };

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            filterButtons.forEach((item) => {
                const isCurrent = item === button;
                item.classList.toggle('is-active', isCurrent);
                item.setAttribute('aria-pressed', String(isCurrent));
            });
            updateCards(button.dataset.filter);
        });
    });

    sortSelect?.addEventListener('change', () => {
        const direction = sortSelect.value === 'oldest' ? 1 : -1;
        [...tripCards]
            .sort((first, second) => first.dataset.date.localeCompare(second.dataset.date) * direction)
            .forEach((card) => tripGrid?.appendChild(card));
    });

    tripCards.forEach((card) => {
        const url = card.dataset.tripUrl;
        if (!url) return;

        card.addEventListener('click', (event) => {
            if (event.target.closest('a, button, input, select, textarea')) return;
            window.location.href = url;
        });
    });

});
