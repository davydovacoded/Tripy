'use strict';

document.addEventListener('DOMContentLoaded', () => {
  window.TripyInitializeSingleDatePickers?.();
    const dialog = document.querySelector('#photo-dialog');
    const form = document.querySelector('#photo-form');
    const fileInput = document.querySelector('#photo-file');
    const fileName = document.querySelector('#photo-file-name');
    const uploadError = document.querySelector('#photo-upload-error');
    const toast = document.querySelector('#gallery-toast');
    const viewer = document.querySelector('#photo-viewer');

    const showToast = (message) => {
        if (!toast) return;
        toast.textContent = message;
        toast.hidden = false;
        window.setTimeout(() => { toast.hidden = true; }, 3000);
    };

    document.querySelectorAll('[data-open-photo-dialog]').forEach((button) => {
        button.addEventListener('click', () => {
            form?.reset();
            form?.querySelectorAll('.field').forEach((field) => field.classList.remove('has-error'));
            form?.querySelectorAll('.field-error').forEach((error) => { error.textContent = ''; });
            form?.querySelectorAll('[data-photo-option]').forEach((option) => {
                option.classList.remove('is-open');
                const content = option.querySelector('.trip-option__content');
                const toggle = option.querySelector('[data-photo-option-toggle]');
                if (content) content.hidden = true;
                toggle?.setAttribute('aria-expanded', 'false');
            });
            window.TripySingleDatePickers?.get('photo-date')?.setDate('');
            if (button.dataset.tripId) form.elements.tripId.value = button.dataset.tripId;
            fileName.textContent = 'JPG, PNG или WebP · до 10 МБ';
            uploadError.hidden = true;
            dialog?.showModal();
        });
    });

    fileInput?.addEventListener('change', () => {
        const file = fileInput.files[0];
        const allowed = ['image/jpeg', 'image/png', 'image/webp'];
        uploadError.hidden = true;
        if (!file) return;
        if (!allowed.includes(file.type) || file.size > 10 * 1024 * 1024) {
            fileInput.value = '';
            fileName.textContent = 'JPG, PNG или WebP · до 10 МБ';
            uploadError.textContent = 'Выберите JPG, PNG или WebP размером до 10 МБ.';
            uploadError.hidden = false;
            return;
        }
        fileName.textContent = file.name;
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        form.querySelectorAll('.field').forEach((field) => field.classList.remove('has-error'));
        const tripField = form.elements.tripId;
        if (!tripField.value) {
            tripField.closest('.field').classList.add('has-error');
            tripField.closest('.field').querySelector('.field-error').textContent = 'Выберите путешествие.';
            tripField.focus();
            return;
        }
        if (!fileInput.files[0]) {
            uploadError.textContent = 'Выберите фотографию.';
            uploadError.hidden = false;
            return;
        }
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            const response = await fetch('actions/photo-save.php', { method: 'POST', body: new FormData(form) });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'Не удалось сохранить фотографию.');
            showToast('Фотография добавлена');
            window.location.reload();
        } catch (error) {
            uploadError.textContent = error.message;
            uploadError.hidden = false;
        } finally {
            submit.disabled = false;
        }
    });

    document.querySelectorAll('.gallery-photo').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelector('#photo-viewer-image').src = button.dataset.photoSrc;
            document.querySelector('#photo-viewer-title').textContent = button.dataset.photoCaption || 'Фотография путешествия';
            viewer?.showModal();
        });
    });

    document.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
    document.querySelectorAll('dialog').forEach((item) => item.addEventListener('click', (event) => { if (event.target === item) item.close(); }));
});
