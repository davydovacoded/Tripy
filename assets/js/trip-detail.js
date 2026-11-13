'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('.trip-detail');
  if (!root) return;

  const tripId = Number(root.dataset.tripId);
  let budgetLimit = root.dataset.budget === '' ? null : Number(root.dataset.budget);
  const csrf = document.querySelector('meta[name="trip-csrf-token"]')?.content || '';
  const initial = JSON.parse(document.querySelector('#trip-initial-data')?.textContent || '{"places":[],"tickets":[],"expenses":[],"photos":[]}');
  let places = initial.places.map(normalize);
  let tickets = initial.tickets.map(normalize);
  let expenses = initial.expenses.map(normalize);
  let photos = (initial.photos || []).map(normalize);
  let placeEditingId = null;
  let ticketEditingId = null;
  let expenseEditingId = null;
  let pendingDelete = null;
  let saving = false;
  let toastTimer;

  const $ = (selector) => document.querySelector(selector);
  const escapeHtml = (value) => String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  function normalize(value) {
    for (const key of ['id','cost','amount','ticketId']) if (value[key] !== undefined && value[key] !== null) value[key] = Number(value[key]);
    for (const key of ['visited','pinned','includeExpense','fromTicket']) if (value[key] !== undefined) value[key] = Boolean(Number(value[key]));
    return value;
  }
  const money = (value) => `${new Intl.NumberFormat('ru-RU',{maximumFractionDigits:2}).format(Number(value)||0)} ₽`;
  const dateText = (value) => value ? new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'long',year:'numeric'}).format(new Date(`${value}T12:00:00`)) : 'Дата не указана';
  const plural = (number, one, few, many) => { const last=number%10,lastTwo=number%100; return lastTwo>=11&&lastTwo<=14?many:last===1?one:last>=2&&last<=4?few:many; };
  const toast = (text) => { const element=$('#trip-toast'); if(!element)return; clearTimeout(toastTimer); element.textContent=text; element.hidden=false; toastTimer=setTimeout(()=>element.hidden=true,2800); };
  const open = (dialog) => dialog?.showModal();
  const singleDatePickers = window.TripyInitializeSingleDatePickers?.() || window.TripySingleDatePickers || new Map();

  async function api(action, data={}, formData=null) {
    const options={method:'POST',headers:{'X-CSRF-Token':csrf}};
    if(formData){formData.set('action',action);formData.set('trip_id',tripId);formData.set('csrf_token',csrf);options.body=formData;}
    else{options.headers['Content-Type']='application/json';options.body=JSON.stringify({action,trip_id:tripId,csrf_token:csrf,...data});}
    const response=await fetch('api/trip-actions.php',options);
    const result=await response.json().catch(()=>({ok:false,message:'Некорректный ответ сервера.'}));
    if(!response.ok||!result.ok)throw new Error(result.message||'Не удалось сохранить изменения.');
    return result;
  }

  const menu = (type,id,actions) => `<div class="item-menu-wrap"><button class="item-menu-button" type="button" data-menu-toggle aria-expanded="false" aria-label="Действия"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg></button><div class="item-menu" hidden>${actions.map(action=>`<button class="${action.danger?'danger':''}" type="button" data-action="${action.key}" data-type="${type}" data-id="${id}">${action.label}</button>`).join('')}</div></div>`;
  const categoryIcon = (category) => {
    const icons={
      'Транспорт':'<path d="M17.8 19.2 16 11l3.5-3.5c1.5-1.5 2-3.5 1-4.5s-3-.5-4.5 1l-3.5 3.5-8.2-1.8-1.8 1.8 6.5 4-3 3-3-.5-1.5 1.5 4 2 2 4L9 20l-.5-3 3-3 4 6.5Z"/>',
      'Проживание':'<path d="M3 18V8M21 18v-6a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v6M3 15h18"/>',
      'Питание':'<path d="M7 3v8M4 3v5a3 3 0 0 0 6 0V3M7 11v10M17 3v18"/>',
      'Развлечения':'<path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a3 3 0 0 0 0 6v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a3 3 0 0 0 0-6Z"/>',
      'Покупки':'<path d="M5 8h14l-1 13H6ZM9 9V6a3 3 0 0 1 6 0v3"/>',
      'Другое':'<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>'
    };
    return `<svg aria-hidden="true" viewBox="0 0 24 24">${icons[category]||icons.Другое}</svg>`;
  };

  function renderPlaces() {
    const list=$('#places-list');
    list.innerHTML=places.length?places.map(place=>`<div class="list-item place-item ${place.visited?'is-visited':''}"><button class="visit-toggle ${place.visited?'is-visited':''}" type="button" data-toggle-visit="${place.id}" aria-pressed="${place.visited}"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></button><div class="list-item__copy"><strong>${escapeHtml(place.name)}</strong><small>${place.visited?'Посещено':'Хочу посетить'}${place.category?` · ${escapeHtml(place.category)}`:''}${place.address?` · ${escapeHtml(place.address)}`:''}</small></div>${menu('place',place.id,[{key:'edit',label:'Редактировать'},{key:'delete',label:'Удалить',danger:true}])}</div>`).join(''):'<p class="empty-list">Добавьте первое место в маршрут.</p>';
    const visited=places.filter(place=>place.visited).length;
    $('#places-summary').textContent=`Посещено ${visited} из ${places.length} ${plural(places.length,'места','мест','мест')}`;
    $('#places-count').textContent=places.length;
  }

  const ticketIcon=()=>'<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a3 3 0 0 0 0 6v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a3 3 0 0 0 0-6Z"/></svg>';
  function renderTickets() {
    const ordered=[...tickets].sort((a,b)=>Number(b.pinned)-Number(a.pinned)||b.id-a.id);
    $('#tickets-list').innerHTML=ordered.length?ordered.map(ticket=>`<div class="list-item"><button class="list-item__main" type="button" data-view-ticket="${ticket.id}"><span class="item-icon">${ticketIcon()}</span><span class="list-item__copy"><strong>${escapeHtml(ticket.name)}</strong><small>${escapeHtml(ticket.type||'Билет')} · ${dateText(ticket.date)} · ${escapeHtml(ticket.format||'ФАЙЛ')}${ticket.pinned?' · Закреплён':''}</small></span></button>${ticket.pinned?'<span class="pin-label" aria-label="Закреплён">Закреплён</span>':''}${menu('ticket',ticket.id,[{key:'edit',label:'Редактировать'},{key:'pin',label:ticket.pinned?'Открепить':'Закрепить'},{key:'delete',label:'Удалить',danger:true}])}</div>`).join(''):'<p class="empty-list">Загруженных билетов пока нет.</p>';
    const pinned=tickets.filter(ticket=>ticket.pinned).length;
    $('#tickets-summary').textContent=`${tickets.length} ${plural(tickets.length,'билет','билета','билетов')} · ${pinned} закреплено`;
    $('#tickets-count').textContent=tickets.length;
  }

  function renderExpenses() {
    const ordered=[...expenses].sort((a,b)=>String(b.date).localeCompare(String(a.date))||b.id-a.id);
    $('#expenses-list').innerHTML=ordered.length?ordered.map(expense=>`<div class="list-item expense-item"><span class="expense-item__icon">${categoryIcon(expense.category)}</span><div class="list-item__copy"><strong>${escapeHtml(expense.name)}</strong><small>${escapeHtml(expense.category)} · ${dateText(expense.date)}${expense.fromTicket?' · Из билета':''}</small></div><strong class="expense-item__amount">${money(expense.amount)}</strong>${menu('expense',expense.id,expense.fromTicket?[{key:'edit-ticket',label:'Редактировать билет'}]:[{key:'edit',label:'Редактировать'},{key:'delete',label:'Удалить',danger:true}])}</div>`).join(''):'<p class="empty-list">Расходов пока нет.</p>';
    const spent=expenses.reduce((sum,expense)=>sum+Number(expense.amount),0);
    const hasBudget=budgetLimit!==null&&Number.isFinite(budgetLimit);
    const left=hasBudget?budgetLimit-spent:null;
    const percent=hasBudget&&budgetLimit>0?Math.min(spent/budgetLimit*100,100):0;
    $('#budget-limit').textContent=hasBudget?money(budgetLimit):'Бюджет не указан';
    $('#budget-spent').textContent=money(spent);
    $('#budget-left').textContent=hasBudget?money(left):'—';
    $('#budget-left').classList.toggle('is-negative',hasBudget&&left<0);
    $('#expenses-summary').textContent=money(spent);
    $('#expenses-count').textContent=`${expenses.length} ${plural(expenses.length,'запись','записи','записей')}`;
    const bar=$('.budget-progress');
    bar.hidden=!hasBudget||budgetLimit<=0;
    bar.setAttribute('aria-valuenow',Math.round(percent));
    bar.querySelector('span').style.width=`${percent}%`;
    bar.classList.toggle('is-over',hasBudget&&left<0);
    const warning=$('#budget-warning');
    warning.hidden=!hasBudget||left>=0;
    warning.textContent=hasBudget&&left<0?`Бюджет превышен на ${money(Math.abs(left))}`:'';
  }

  function renderPhotos() {
    const list=$('#photos-list');
    list.innerHTML=photos.length?photos.map(photo=>`<article class="trip-photo"><button type="button" data-view-photo="${photo.id}"><img src="${escapeHtml(photo.filePath)}" alt="${escapeHtml(photo.caption||'Фотография путешествия')}" loading="lazy"></button><button class="trip-photo__delete" type="button" data-delete-photo="${photo.id}" aria-label="Удалить фотографию"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15"/></svg></button></article>`).join(''):'<div class="empty-list photo-empty"><strong>Фотографий пока нет</strong><span>Добавьте первый момент этого путешествия.</span></div>';
    $('#photos-count').textContent=photos.length;
  }

  document.querySelectorAll('[data-trip-tab]').forEach(button=>button.addEventListener('click',()=>{
    document.querySelectorAll('[data-trip-tab]').forEach(tab=>{const active=tab===button;tab.classList.toggle('is-active',active);tab.setAttribute('aria-selected',String(active));});
    document.querySelectorAll('[data-trip-panel]').forEach(panel=>{const active=panel.dataset.tripPanel===button.dataset.tripTab;panel.hidden=!active;panel.classList.toggle('is-active',active);});
  }));

  function resetErrors(form){form.querySelectorAll('.field').forEach(item=>item.classList.remove('has-error'));form.querySelectorAll('.field-error').forEach(item=>item.textContent='');}
  function fieldError(input,text){input.closest('.field')?.classList.add('has-error');const output=input.closest('.field')?.querySelector('.field-error');if(output)output.textContent=text;}
  function placeForm(place=null){const form=$('#place-form');form.reset();resetErrors(form);placeEditingId=place?.id??null;$('#place-dialog-title').textContent=place?'Редактировать место':'Добавить место';$('#place-submit').textContent=place?'Сохранить изменения':'Сохранить место';if(place){form.elements.name.value=place.name;form.elements.category.value=place.category||'';form.elements.address.value=place.address||'';form.elements.note.value=place.note||'';form.elements.visited.checked=place.visited;}open($('#place-dialog'));}
  function setTicketOption(name,isOpen){const option=document.querySelector(`[data-ticket-option="${name}"]`);if(!option)return;const content=option.querySelector('.trip-option__content'),toggle=option.querySelector('[data-ticket-option-toggle]');content.hidden=!isOpen;option.classList.toggle('is-open',isOpen);toggle.setAttribute('aria-expanded',String(isOpen));}
  function ticketForm(ticket=null){const form=$('#ticket-form');form.reset();resetErrors(form);ticketEditingId=ticket?.id??null;$('#ticket-dialog-title').textContent=ticket?'Редактировать билет':'Добавить билет';form.elements.file.required=!ticket;$('#ticket-file-name').textContent=ticket?'Новый файл не обязателен':'PDF, JPG или PNG · до 10 МБ';if(ticket){form.elements.name.value=ticket.name;form.elements.type.value=ticket.type;form.elements.note.value=ticket.note||'';form.elements.pinned.checked=ticket.pinned;form.elements.cost.value=ticket.cost||'';form.elements.includeExpense.checked=ticket.includeExpense;}singleDatePickers.get('ticket-date')?.setDate(ticket?.date||'');setTicketOption('date',Boolean(ticket?.date));setTicketOption('note',Boolean(ticket?.note));setTicketOption('pinned',Boolean(ticket?.pinned));setTicketOption('cost',Number(ticket?.cost)>0);updateTicketToggle();open($('#ticket-dialog'));}
  function setExpenseOption(name,isOpen){const option=document.querySelector(`[data-expense-option="${name}"]`);if(!option)return;const content=option.querySelector('.trip-option__content'),toggle=option.querySelector('[data-expense-option-toggle]');content.hidden=!isOpen;option.classList.toggle('is-open',isOpen);toggle.setAttribute('aria-expanded',String(isOpen));}
  function expenseForm(expense=null){if(expense?.fromTicket){const ticket=tickets.find(item=>item.id===expense.ticketId);if(ticket)ticketForm(ticket);return;}const form=$('#expense-form');form.reset();resetErrors(form);expenseEditingId=expense?.id??null;$('#expense-dialog-title').textContent=expense?'Редактировать расход':'Добавить расход';$('#expense-submit').textContent=expense?'Сохранить изменения':'Добавить расход';if(expense){form.elements.name.value=expense.name==='Расход'?'':expense.name;form.elements.amount.value=expense.amount;form.elements.category.value=expense.category||'';form.elements.note.value=expense.note||'';}singleDatePickers.get('expense-date')?.setDate(expense?.date||'');setExpenseOption('date',Boolean(expense?.date));setExpenseOption('note',Boolean(expense?.note));open($('#expense-dialog'));}
  function updateTicketToggle(){const valid=Number($('#ticket-cost').value)>0;$('#ticket-include-expense').disabled=!valid;if(!valid)$('#ticket-include-expense').checked=false;}

  document.querySelectorAll('[data-open-dialog]').forEach(button=>button.addEventListener('click',()=>{const id=button.dataset.openDialog;if(id==='place-dialog')placeForm();else if(id==='ticket-dialog')ticketForm();else if(id==='expense-dialog')expenseForm();else if(id==='photo-dialog'){const form=$('#photo-form');form?.reset();form?.querySelectorAll('[data-photo-option]').forEach(option=>{option.classList.remove('is-open');const content=option.querySelector('.trip-option__content');const toggle=option.querySelector('[data-photo-option-toggle]');if(content)content.hidden=true;toggle?.setAttribute('aria-expanded','false');});singleDatePickers.get('photo-date')?.setDate('');$('#photo-file-name').textContent='JPG, PNG или WebP · до 10 МБ';$('#photo-upload-error').hidden=true;open($('#photo-dialog'));}else open(document.getElementById(id));}));
  $('#place-form')?.addEventListener('submit',async event=>{event.preventDefault();const form=event.currentTarget,name=form.elements.name.value.trim();if(!name){fieldError(form.elements.name,'Введите название места.');return;}try{const value={id:placeEditingId,name,category:form.elements.category.value,address:form.elements.address.value.trim(),note:form.elements.note.value.trim(),visited:form.elements.visited.checked};const result=await api('place_save',value);if(placeEditingId)places=places.map(place=>place.id===placeEditingId?{...place,...value}:place);else places.unshift({id:Number(result.id),...value});renderPlaces();$('#place-dialog').close();toast(placeEditingId?'Место обновлено':'Место добавлено');}catch(error){toast(error.message);}});
  $('#places-list')?.addEventListener('click',async event=>{const button=event.target.closest('[data-toggle-visit]');if(!button)return;const id=Number(button.dataset.toggleVisit);try{await api('place_toggle',{id});places=places.map(place=>place.id===id?{...place,visited:!place.visited}:place);renderPlaces();toast('Статус места обновлён. Расходы не изменены.');}catch(error){toast(error.message);}});
  document.querySelectorAll('[data-ticket-option-toggle]').forEach(toggle=>toggle.addEventListener('click',()=>{const option=toggle.closest('[data-ticket-option]'),content=option.querySelector('.trip-option__content'),expanded=content.hidden;content.hidden=!expanded;option.classList.toggle('is-open',expanded);toggle.setAttribute('aria-expanded',String(expanded));}));
  document.querySelectorAll('[data-expense-option-toggle]').forEach(toggle=>toggle.addEventListener('click',()=>{const option=toggle.closest('[data-expense-option]'),content=option.querySelector('.trip-option__content'),expanded=content.hidden;content.hidden=!expanded;option.classList.toggle('is-open',expanded);toggle.setAttribute('aria-expanded',String(expanded));}));
  $('#ticket-cost')?.addEventListener('input',updateTicketToggle);
  $('#ticket-file')?.addEventListener('change',event=>{$('#ticket-file-name').textContent=event.target.files[0]?.name||'PDF, JPG или PNG · до 10 МБ';});
  $('#ticket-form')?.addEventListener('submit',async event=>{event.preventDefault();if(saving)return;const form=event.currentTarget;if(!form.elements.name.value.trim()){fieldError(form.elements.name,'Введите название билета.');return;}saving=true;try{const data=new FormData(form);data.set('id',ticketEditingId||0);await api('ticket_save',{},data);window.location.reload();}catch(error){$('#ticket-upload-error').textContent=error.message;$('#ticket-upload-error').hidden=false;}finally{saving=false;}});
  $('#expense-form')?.addEventListener('submit',async event=>{event.preventDefault();if(saving)return;const form=event.currentTarget,name=form.elements.name.value.trim()||'Расход',amount=Number(form.elements.amount.value);if(!Number.isFinite(amount)||amount<=0){fieldError(form.elements.amount,'Введите сумму больше нуля.');return;}saving=true;try{const value={id:expenseEditingId,name,amount,category:form.elements.category.value||'Другое',date:form.elements.date.value,note:form.elements.note.value.trim()};const result=await api('expense_save',value);value.date=result.date;if(expenseEditingId)expenses=expenses.map(item=>item.id===expenseEditingId?{...item,...value}:item);else expenses.unshift({id:Number(result.id),...value});renderExpenses();$('#expense-dialog').close();toast(expenseEditingId?'Расход обновлён':'Расход добавлен');}catch(error){toast(error.message);}finally{saving=false;}});

  $('[data-open-budget-dialog]')?.addEventListener('click',()=>open($('#budget-dialog')));
  $('#budget-form')?.addEventListener('submit',async event=>{event.preventDefault();const form=event.currentTarget,raw=form.elements.budget.value.trim(),value=raw===''?null:Number(raw);if(value!==null&&(!Number.isFinite(value)||value<0)){fieldError(form.elements.budget,'Введите положительную сумму или оставьте поле пустым.');return;}try{const result=await api('trip_budget',{budget:raw});budgetLimit=result.budget===null?null:Number(result.budget);renderExpenses();$('#budget-dialog').close();toast('Бюджет обновлён');}catch(error){toast(error.message);}});

  const photoFile=$('#photo-file'),photoError=$('#photo-upload-error');
  photoFile?.addEventListener('change',()=>{const file=photoFile.files[0];photoError.hidden=true;if(!file)return;if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>10*1024*1024){photoFile.value='';photoError.textContent='Выберите JPG, PNG или WebP размером до 10 МБ.';photoError.hidden=false;return;}$('#photo-file-name').textContent=file.name;});
  $('#photo-form')?.addEventListener('submit',async event=>{event.preventDefault();const form=event.currentTarget;if(!photoFile.files[0]){photoError.textContent='Выберите фотографию.';photoError.hidden=false;return;}const submit=form.querySelector('[type="submit"]');submit.disabled=true;try{const response=await fetch('actions/photo-save.php',{method:'POST',body:new FormData(form)});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.message||'Не удалось сохранить фотографию.');photos.unshift(normalize(result.photo));renderPhotos();$('#photo-dialog').close();form.reset();$('#photo-file-name').textContent='JPG, PNG или WebP · до 10 МБ';toast('Фотография добавлена');}catch(error){photoError.textContent=error.message;photoError.hidden=false;}finally{submit.disabled=false;}});
  $('#photos-list')?.addEventListener('click',event=>{const view=event.target.closest('[data-view-photo]');if(view){const photo=photos.find(item=>item.id===Number(view.dataset.viewPhoto));if(!photo)return;$('#trip-photo-viewer-image').src=photo.filePath;$('#trip-photo-viewer-title').textContent=photo.caption||'Фотография путешествия';open($('#trip-photo-viewer'));return;}const remove=event.target.closest('[data-delete-photo]');if(remove)askDelete('photo',Number(remove.dataset.deletePhoto));});

  function askDelete(type,id){pendingDelete={type,id};$('#confirm-title').textContent=`Удалить ${{place:'место',ticket:'билет',expense:'расход',photo:'фотографию'}[type]}?`;$('#confirm-message').textContent=type==='ticket'&&expenses.some(expense=>expense.fromTicket&&expense.ticketId===id)?'Вместе с билетом будет удалён связанный расход.':'Действие нельзя отменить.';open($('#confirm-dialog'));}
  $('#confirm-delete')?.addEventListener('click',async()=>{if(!pendingDelete)return;try{await api(`${pendingDelete.type}_delete`,{id:pendingDelete.id});if(pendingDelete.type==='place'){places=places.filter(item=>item.id!==pendingDelete.id);renderPlaces();}if(pendingDelete.type==='ticket'){tickets=tickets.filter(item=>item.id!==pendingDelete.id);expenses=expenses.filter(item=>item.ticketId!==pendingDelete.id);renderTickets();renderExpenses();}if(pendingDelete.type==='expense'){expenses=expenses.filter(item=>item.id!==pendingDelete.id);renderExpenses();}if(pendingDelete.type==='photo'){photos=photos.filter(item=>item.id!==pendingDelete.id);renderPhotos();}$('#confirm-dialog').close();toast('Запись удалена');pendingDelete=null;}catch(error){toast(error.message);}});
  document.addEventListener('click',async event=>{const toggle=event.target.closest('[data-menu-toggle]');if(toggle){const current=toggle.nextElementSibling;document.querySelectorAll('.item-menu').forEach(item=>{if(item!==current)item.hidden=true;});current.hidden=!current.hidden;return;}const actionButton=event.target.closest('[data-action]');if(!actionButton)return;const id=Number(actionButton.dataset.id),type=actionButton.dataset.type,action=actionButton.dataset.action;if(action==='delete')askDelete(type,id);if(action==='edit'&&type==='place')placeForm(places.find(item=>item.id===id));if(action==='edit'&&type==='ticket')ticketForm(tickets.find(item=>item.id===id));if(action==='edit'&&type==='expense')expenseForm(expenses.find(item=>item.id===id));if(action==='edit-ticket'){const expense=expenses.find(item=>item.id===id);ticketForm(tickets.find(item=>item.id===expense?.ticketId));}if(action==='pin'){try{await api('ticket_pin',{id});tickets=tickets.map(item=>item.id===id?{...item,pinned:!item.pinned}:item);renderTickets();}catch(error){toast(error.message);}}});
  $('#tickets-list')?.addEventListener('click',event=>{const button=event.target.closest('[data-view-ticket]');if(!button)return;const ticket=tickets.find(item=>item.id===Number(button.dataset.viewTicket));if(!ticket)return;const previewUrl=`download-ticket.php?id=${ticket.id}`;const pdf=$('#ticket-preview-pdf'),image=$('#ticket-preview-image'),fallback=$('#ticket-preview-fallback'),format=String(ticket.format||'').toUpperCase();pdf.hidden=true;image.hidden=true;fallback.hidden=true;pdf.src='';image.src='';if(format==='PDF'){pdf.src=previewUrl;pdf.hidden=false;}else if(['JPG','JPEG','PNG'].includes(format)){image.src=previewUrl;image.alt=`Билет «${ticket.name}»`;image.hidden=false;}else{fallback.hidden=false;}$('#ticket-viewer-title').textContent=ticket.name;$('#ticket-viewer-meta').textContent=`${ticket.type} · ${dateText(ticket.date)} · ${ticket.format}`;$('#ticket-download').href=`${previewUrl}&download=1`;open($('#ticket-viewer'));});
  document.querySelectorAll('[data-dialog-close]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog')?.close()));
  document.querySelectorAll('dialog:not(#trip-form-dialog)').forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();}));

  renderPlaces();
  renderTickets();
  renderExpenses();
  renderPhotos();
  updateTicketToggle();
});
