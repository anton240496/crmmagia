// ==================== ОСНОВНАЯ ИНИЦИАЛИЗАЦИЯ ====================

document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM loaded, initializing editors');
    initializeEditors();
    setupEventListeners();
    initEditorObservers();
});

// ==================== ФУНКЦИИ РЕДАКТОРА ====================

function initializeEditors() {
    const editors = document.querySelectorAll('.file-content-editor');
    console.log(`Found ${editors.length} editors`);
    editors.forEach(editor => {
        setupEditor(editor);
    });
}
// Добавьте console.log для проверки
// Вместо .hover() используем .on() с делегированием
$(document).on('mouseenter', '.inst1, .instcalk', function () {
    $('.file-content-editor .p').not('.table-container').addClass('hovred');
});

$(document).on('mouseleave', '.inst1, .instcalk', function () {
    $('.file-content-editor .p').removeClass('hovred');
});

// ==================== ОБНОВЛЕННАЯ ФУНКЦИЯ SETUP EDITOR ====================

function setupEditor(editor) {
    // Получаем leadId из атрибута или ищем в родительских элементах
    let leadId = editor.getAttribute('data-lead-id');
    let dialogId = editor.getAttribute('data-dialog-id');

    if (!leadId) {
        const leadElement = editor.closest('[data-lead-id]');
        if (leadElement) {
            leadId = leadElement.dataset.leadId;
            editor.setAttribute('data-lead-id', leadId);
        }
    }

    if (!dialogId) {
        const dialogElement = editor.closest('[data-dialog-id]');
        if (dialogElement) {
            dialogId = dialogElement.dataset.dialogId;
            editor.setAttribute('data-dialog-id', dialogId);
        }
    }

    if (!leadId) {
        // console.error('Editor is not inside an element with data-lead-id');
        // console.log('Editor parent structure:', editor.parentElement);
        return;
    }

    // Создаем уникальный ключ для хранения
    const storageKey = `crm_editor_${leadId}${dialogId ? '_' + dialogId : ''}`;
    console.log(`Setting up editor with storage key: ${storageKey}`);

    // Загрузка сохраненного содержимого
    const saved = localStorage.getItem(storageKey);
    if (saved && !editor.innerHTML.includes('document-header')) {
        editor.innerHTML = saved;
        console.log(`Loaded saved content for editor ${storageKey}`);
    }

    // Сохранение при изменении
    editor.addEventListener('input', function () {
        localStorage.setItem(storageKey, editor.innerHTML);
        console.log(`Saved content for editor ${storageKey}`);
    });

    // Фокус на контенте при клике на заголовок
    editor.addEventListener('click', function (e) {
        if (e.target.classList.contains('document-header')) {
            const contentDiv = editor.querySelector('.document-content');
            if (contentDiv) {
                const range = document.createRange();
                const sel = window.getSelection();
                range.setStart(contentDiv, 0);
                range.collapse(true);
                sel.removeAllRanges();
                sel.addRange(range);
            }
        }
    });

    console.log(`Editor setup complete for lead: ${leadId}, dialog: ${dialogId}`);
}

// ==================== ОБРАБОТЧИКИ СОБЫТИЙ ====================

function setupEventListeners() {
    // ТОЛЬКО для кнопок форматирования (не таблиц)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.format-btn');
        if (btn && btn.classList.contains('reset-template-btn')) {
            e.preventDefault();
            handleResetTemplate(btn);
        }
    });
}

// ==================== ПЕРЕИНИЦИАЛИЗАЦИЯ ДЛЯ ДИНАМИЧЕСКОГО КОНТЕНТА ====================

function reinitializeEditorsForDialog(dialogElement) {
    console.log('Reinitializing editors for dialog', dialogElement);

    const editors = dialogElement.querySelectorAll('.file-content-editor');
    console.log(`Found ${editors.length} editors in dialog`);

    editors.forEach(editor => {
        setupEditor(editor);
    });
}

// ==================== ФУНКЦИЯ ДЛЯ ПРАВИЛЬНОГО ПОИСКА РЕДАКТОРОВ В ДИАЛОГАХ ====================

function reinitializeDialogEditors(dialogElement) {
    console.log('Reinitializing editors for dialog', dialogElement);

    const editors = dialogElement.querySelectorAll('.file-content-editor');
    console.log(`Found ${editors.length} editors in dialog`);

    editors.forEach(editor => {
        // Ищем data-lead-id в родительских элементах
        let leadElement = editor.closest('[data-lead-id]');
        let dialogElement = editor.closest('[data-dialog-id]');

        if (!leadElement) {
            // Если не нашли в ближайших родителях, ищем в более высоких уровнях
            leadElement = document.querySelector(`#dialog-panel-${dialogElement?.dataset.dialogId}`) ||
                document.querySelector(`[data-lead-id]`);
        }

        if (leadElement) {
            const leadId = leadElement.dataset.leadId;
            const dialogId = dialogElement ? dialogElement.dataset.dialogId : 'unknown';

            console.log('Editor context:', { leadId, dialogId });

            // Устанавливаем атрибуты для идентификации
            editor.setAttribute('data-lead-id', leadId);
            editor.setAttribute('data-dialog-id', dialogId);

            setupEditor(editor);
        } else {
            console.warn('Editor is not inside an element with data-lead-id');
        }
    });
}

// Функция для вызова извне (из crm.js)
window.reinitializeDialogEditors = function (dialogElement) {
    reinitializeEditorsForDialog(dialogElement);
};

function handleResetTemplate(btn) {
    console.log('Reset template button clicked');
    const fileWindow = btn.closest('.file-creation-window');
    if (fileWindow) {
        console.log('File window found', fileWindow);
        resetEditorToTemplate(fileWindow);
    } else {
        console.error('File window not found');
    }
}

// ==================== РАБОТА СО СПИСКАМИ И СТИЛЯМИ ====================

function initEditorObservers() {
    const editors = document.querySelectorAll('.file-content-editor');
    editors.forEach(editor => {
        setupEditorObserver(editor);
        removeFontSpans(editor);
    });
}

function removeFontSpans(element) {
    const spans = element.querySelectorAll('span');
    spans.forEach(span => {
        if (span.style && (span.style.fontSize || span.style.color || span.style.fontFamily)) {
            const text = document.createTextNode(span.textContent);
            span.parentNode.replaceChild(text, span);
        }
    });
}

function setupEditorObserver(editor) {
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1 && node.tagName === 'SPAN') {
                        removeFontSpans(node.parentNode);
                    }
                });
            }
        });
    });

    observer.observe(editor, {
        childList: true,
        subtree: true
    });
}

// ==================== ФУНКЦИИ ШАБЛОНОВ И УВЕДОМЛЕНИЙ ====================




function resetEditorToTemplate(fileWindow) {
    console.log('🔄 === resetEditorToTemplate ВЫЗВАН ===');

    const editor = fileWindow.querySelector('.file-content-editor');
    if (editor) {
        if (confirm('Сбросить редактор к шаблону? Текущее содержимое будет удалено.')) {
            console.log('🚀 Отправка AJAX запроса...');

            let dialogId = 0;

            if (editor.dataset.dialogId) {
                dialogId = editor.dataset.dialogId;
            } else {
                const activeDialog = fileWindow.closest('.dialog-panel')?.querySelector('.dialog-item.active');
                dialogId = activeDialog?.dataset.dialogId || 0;
            }

            console.log('🔍 Найден dialogId:', dialogId);

            const emailElement = editor.querySelector('#kp-sender-email');
            let currentEmail = '';
            if (emailElement) {
                currentEmail = emailElement.textContent.trim();
                console.log('📧 Найдена текущая почта для сохранения:', currentEmail);
            }

            // ИСПРАВЛЕНИЕ: Определяем, находимся ли мы на странице настроек CRM
            // Проверяем наличие специфичных для настроек элементов или классов
            const isCrmSettings = document.querySelector('body')?.classList.contains('settings-crm-page') ||
                window.location.href.includes('crm-settings') ||
                document.querySelector('[data-is-crm-settings]');

            // ИСПРАВЛЕНИЕ: Добавляем параметр is_crm_settings в запрос
            const bodyData = new URLSearchParams({
                action: 'get_editor_template',
                dialog_id: dialogId,
                is_crm_settings: isCrmSettings ? '1' : '0'  // ← Добавляем флаг настроек CRM
            });

            fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: bodyData
            })
                .then(response => {
                    console.log('📨 Статус ответа:', response.status, response.statusText);

                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('❌ Ошибка парсинга JSON:', e.message);
                            throw new Error('Сервер вернул не JSON: ' + text.substring(0, 200));
                        }
                    });
                })
                .then(data => {
                    console.log('✅ JSON распарсен успешно:', data);

                    if (data.success) {
                        editor.innerHTML = data.data;
                        console.log('✅ Editor сброшен к шаблону');

                        // ВОССТАНАВЛИВАЕМ ПОЧТУ
                        if (currentEmail) {
                            const newEmailElement = editor.querySelector('#kp-sender-email');
                            if (newEmailElement) {
                                console.log('🔄 Восстанавливаем почту:', currentEmail);
                                newEmailElement.textContent = currentEmail;
                            } else {
                                console.warn('⚠️ Элемент #kp-sender-email не найден после сброса');
                            }
                        }

                        // ОЧИЩАЕМ LOCALSTORAGE
                        const leadElement = fileWindow.closest('[data-lead-id]');
                        if (leadElement && leadElement.dataset.leadId) {
                            const leadId = leadElement.dataset.leadId;
                            const storageKey = `crm_editor_${leadId}`;
                            localStorage.removeItem(storageKey);
                            console.log('🗑️ Удален localStorage для leadId:', leadId);
                        }

                        showNotification('Редактор сброшен к шаблону', 'success');

                    } else {
                        throw new Error('Ошибка в данных: ' + (data.data || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('💥 ФАТАЛЬНАЯ ОШИБКА:', error);

                    const fallbackTemplate = `
                <div class="document-header">
                    <h1>CRM-система</h1>
                    <div class="document-subtitle">Создать файл</div>
                </div>
                <div class="document-content">
                    <p>Здесь будет содержимое вашего документа...</p>
                </div>`;
                    editor.innerHTML = fallbackTemplate;

                    if (currentEmail) {
                        const emailHTML = `<td class="avatar_mail avatar_text" id="kp-sender-email">${currentEmail}</td>`;
                        const table = editor.querySelector('table');
                        if (table) {
                            const row = table.insertRow();
                            row.innerHTML = emailHTML;
                        }
                        console.log('📧 Восстановлена почта в заготовке:', currentEmail);
                    }

                    showNotification('Использован стандартный шаблон', 'warning');
                });
        }
    } else {
        console.error('❌ Editor not found in file window');
    }
}

function showNotification(message, type) {
    console.log(`Showing notification: ${message}, type: ${type}`);
    const notification = document.createElement('div');
    notification.className = `crm-notification notice notice-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        padding: 10px 15px;
        border-radius: 4px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        background: ${type === 'success' ? '#d4edda' : '#f8d7da'};
        color: ${type === 'success' ? '#155724' : '#721c24'};
        border: 1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'};
    `;

    document.body.appendChild(notification);

    setTimeout(function () {
        notification.remove();
    }, 3000);
}

// ==================== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ====================

function getEditorContent(leadId) {
    const fileWindow = document.querySelector(`#file-window-${leadId}`);
    if (fileWindow) {
        const editor = fileWindow.querySelector('.file-content-editor');
        return editor ? editor.innerHTML : '';
    }
    return '';
}

function getEditorText(leadId) {
    const fileWindow = document.querySelector(`#file-window-${leadId}`);
    if (fileWindow) {
        const editor = fileWindow.querySelector('.file-content-editor');
        return editor ? editor.innerText || editor.textContent : '';
    }
    return '';
}