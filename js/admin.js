function previewImage(event) {
    const file = event.target.files[0];
    if (!file) {
        return;
    }

    const reader = new FileReader();
    reader.onload = loadedEvent => {
        const preview = document.getElementById('imagePreview');
        if (preview) {
            preview.innerHTML = '<img src="' + loadedEvent.target.result + '" class="image-preview">';
        }
    };
    reader.readAsDataURL(file);
}

function initAdminCalendar() {
    const dialog = document.getElementById('calendarEventDialog');
    const form = document.getElementById('calendarEventForm');
    if (!dialog || !form) {
        return;
    }

    const dateInput = document.getElementById('calendarEventDate');
    const startInput = document.getElementById('calendarEventStart');
    const endInput = document.getElementById('calendarEventEnd');
    const storedTimeInput = document.getElementById('calendarStoredTime');
    const titleInput = form.querySelector('[name="title"]');

    const setEndTime = () => {
        const [hours, minutes] = startInput.value.split(':').map(Number);
        const endMinutes = (hours * 60 + minutes + 60) % (24 * 60);
        endInput.value = `${String(Math.floor(endMinutes / 60)).padStart(2, '0')}:${String(endMinutes % 60).padStart(2, '0')}`;
    };

    startInput.addEventListener('change', setEndTime);
    form.addEventListener('submit', () => {
        storedTimeInput.value = `${startInput.value} - ${endInput.value}`;
    });

    document.querySelectorAll('[data-calendar-create]').forEach(button => {
        button.addEventListener('click', () => {
            dateInput.value = button.dataset.date || '';
            startInput.value = button.dataset.time || '09:00';
            setEndTime();
            dialog.showModal();
            titleInput.focus();
        });
    });

    dialog.querySelectorAll('[data-calendar-close]').forEach(button => {
        button.addEventListener('click', () => dialog.close());
    });

    dialog.addEventListener('click', event => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
}

document.querySelectorAll('[data-image-preview]').forEach(input => {
    input.addEventListener('change', previewImage);
});

document.querySelectorAll('[data-confirm-delete]').forEach(form => {
    form.addEventListener('submit', event => {
        if (!confirm('Supprimer cet événement ?')) {
            event.preventDefault();
        }
    });
});

initAdminCalendar();

