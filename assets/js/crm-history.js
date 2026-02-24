
// 🔥 ФУНКЦИЯ ДЛЯ ГЕНЕРАЦИИ ИМЕНИ ПАПКИ НА КЛИЕНТЕ
function generateFolderNameFromLead(leadId, dialogId) {
    // Временная реализация - нужно будет доработать когда будут реальные данные
    // Сейчас используем шаблон: {leadId}_заявка_клиент_диалог{dialogId}
    return `${leadId}_заявка_клиент_диалог${dialogId}`;
}

// 🔥 ФУНКЦИЯ ДЛЯ ПОЛУЧЕНИЯ ПРАВИЛЬНОГО ПУТИ К ФАЙЛУ
function getOutgoingFileUrl(fileName, leadId, dialogId) {
    const folderName = generateFolderNameFromLead(leadId, dialogId);
    const encodedFileName = encodeURIComponent(fileName);
    return `${crm_ajax.home_url || window.location.origin}/wp-content/uploads/crm_files/от_меня/${folderName}/${encodedFileName}`;
}
// 
function loadFileSettingsForDialog(dialogId) {
    return new Promise((resolve) => {
        // Пробуем загрузить из localStorage
        const savedSetting = localStorage.getItem('messagesHistoryEnabled');
        if (savedSetting !== null) {
            // ИСПРАВЛЕННАЯ СТРОКА:
            window.messagesHistoryEnabled = savedSetting === 'false';
            console.log('📁 Настройка из localStorage:', window.messagesHistoryEnabled);
            resolve();
            return;
        }

        // Если в localStorage нет, загружаем с сервера
        $.ajax({
            url: crm_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_file_setting',
                dialog_id: dialogId,
                nonce: crm_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    window.messagesHistoryEnabled = response.data.files_enabled;
                    localStorage.setItem('messagesHistoryEnabled', response.data.files_enabled.toString());
                    console.log('📁 Настройка с сервера:', window.messagesHistoryEnabled);
                }
                resolve();
            },
            error: function () {
                // При ошибке используем значение по умолчанию
                window.messagesHistoryEnabled = false;
                console.log('📁 Настройка по умолчанию:', window.messagesHistoryEnabled);
                resolve();
            }
        });
    });
}

async function showMessagesHistory(leadId, dialogId) {
    console.log('📨 Показать историю для диалога:', dialogId);

    // Закрываем существующее окно если есть
    closeMessagesHistory();

    //  ЗАГРУЖАЕМ НАСТРОЙКИ ПЕРЕД СОЗДАНИЕМ ОКНА
    await loadFileSettingsForDialog(dialogId);

    const isHistoryEnabled = window.messagesHistoryEnabled !== false;

    // Создаем модальное окно
    const modalHtml = `
        <div class="messages-history-modal" style="
          position: absolute;
    top: 65%;
    right: 50px;
    width: 300px;
    transform: translate(-0%, 60%);
    background: #8db6dd;
    padding: 5px;
    z-index: 10000;
    max-width: 800px;
    /* width: 95%; */
    font-size: 14px;
    max-height: 85vh;
    overflow: auto;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    z-index: 10000000000;
        ">
            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 5px; border-bottom: 1px solid #eee;">
                <h3 style="margin: 0; color: #333">
                    История сообщений 

                </h3>
                <strong style="color: red; font-size:10px;  margin-left:10px">приход входящих сообщений будут добавлены в будущих обновлениях</strong>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <!-- ✅ ПЕРЕКЛЮЧАТЕЛЬ ИСТОРИИ ВХОДЯЩИХ -->
                    <div class="history-toggle-container" style="display: flex; align-items: center; gap: 8px;">
                    </div>

                 

                    <button type="button" class="close-history-modal" style="
                        background: none; 
                        border: none; 
                        font-size: 24px; 
                        cursor: pointer; 
                        color: #666; 
                        padding: 0; 
                        width: 30px; 
                        height: 30px; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center;
                    ">×</button>
                </div>
            </div>
            <div class="messages-history-content" >
                <div style="text-align: center; padding: 40px;">
                    <div class="spinner" style="border: 3px solid #f3f3f3; border-top: 3px solid #007cba; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px;"></div>
                    <p>Загрузка истории сообщений...</p>
                </div>
            </div>
        </div>
        <div class="modal-overlay" style="
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
        "></div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // ✅ ОБРАБОТЧИК ДЛЯ ПЕРЕКЛЮЧАТЕЛЯ ВХОДЯЩИХ
  



    // Загружаем историю и запускаем автообновление
    loadMessagesHistory(leadId, dialogId);
    startAutoRefresh(leadId, dialogId);

    // Обработчики закрытия
    document.querySelector('.close-history-modal').onclick = closeMessagesHistory;
    document.querySelector('.modal-overlay').onclick = closeMessagesHistory;

    // Закрытие по ESC
    document.addEventListener('keydown', handleEscapeKey);
}



// ✅ ФУНКЦИЯ ПЕРЕКЛЮЧЕНИЯ ИСТОРИИ ВХОДЯЩИХ СООБЩЕНИЙ

// ✅ ФУНКЦИЯ ПЕРЕКЛЮЧЕНИЯ РЕЖИМА ЗАГРУЗКИ ФАЙЛОВ
// ✅ ОБНОВЛЕННАЯ ФУНКЦИЯ ПЕРЕКЛЮЧЕНИЯ (С СОХРАНЕНИЕМ НА СЕРВЕРЕ)

function toggleMessagesHistory(enabled, leadId, dialogId) {
    console.log(`🔄 Переключение режима файлов: ${enabled ? 'ВКЛ' : 'ВЫКЛ'}`);

   
    const originalState = toggleCheckbox.checked;
    toggleCheckbox.disabled = true;

    // Сохраняем в localStorage для мгновенного отклика
    localStorage.setItem('messagesHistoryEnabled', enabled.toString());
    window.messagesHistoryEnabled = enabled;

    //  СОХРАНЯЕМ НАСТРОЙКУ НА СЕРВЕРЕ
    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'update_file_setting',
            dialog_id: dialogId,
            files_enabled: enabled ? '1' : '0',
            nonce: crm_ajax.nonce
        },
        success: function (response) {
            if (response.success) {
                showTempNotification(response.data.message, 'success');
                updateHistoryUI(enabled, leadId, dialogId);
                console.log(`✅ Настройка файлов сохранена на сервере: ${enabled ? 'ВКЛ' : 'ВЫКЛ'}`);
            }
        },
        error: function (xhr, status, error) {
            console.error('❌ Ошибка сохранения настроек:', error);
            showTempNotification('Ошибка сохранения настроек', 'error');
            // Возвращаем checkbox в исходное состояние
            toggleCheckbox.checked = originalState;
        },
        complete: function () {
            toggleCheckbox.disabled = false;
        }
    });
}

// ✅ ОБНОВЛЕНИЕ НАСТРОЕК ФАЙЛОВ НА СЕРВЕРЕ
function updateServerFileSetting(enabled, dialogId) {
    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'update_file_setting',
            dialog_id: dialogId,
            files_enabled: enabled ? '1' : '0',
            nonce: crm_ajax.nonce
        },
        success: function (response) {
            if (response.success) {
                console.log('✅ Настройки файлов обновлены на сервере');
            }
        },
        error: function (xhr, status, error) {
            console.error('❌ Ошибка обновления настроек:', error);
        }
    });
}


// ✅ ФУНКЦИЯ ОБНОВЛЕНИЯ ИНТЕРФЕЙСА ПОСЛЕ ПЕРЕКЛЮЧЕНИЯ
function updateHistoryUI(enabled, leadId, dialogId) {
    const modal = document.querySelector('.messages-history-modal');
    if (!modal) return;

    // Обновляем заголовок
    const title = modal.querySelector('h3');
    if (title) {
        const existingBadge = title.querySelector('.status-badge');
        if (existingBadge) existingBadge.remove();

        if (!enabled) {
            const badge = document.createElement('span');
            title.appendChild(badge);
        }
    }

    // Перезагружаем историю чтобы применить фильтр
    loadMessagesHistory(leadId, dialogId);
}

// ✅ ОТДЕЛЬНАЯ ФУНКЦИЯ ДЛЯ ОБРАБОТКИ КНОПКИ ОБНОВЛЕНИЯ
function handleRefreshButton(leadId, dialogId, buttonElement) {
    console.log('🔄 Ручное обновление...');

    const originalHtml = buttonElement.innerHTML;

    // ✅ МАЛЕНЬКИЙ ИНДИКАТОР ЗАГРУЗКИ
    buttonElement.innerHTML = '🔄 <div style="display: inline-block; width: 12px; height: 12px; border: 2px solid #f3f3f3; border-top: 2px solid #ffffff; border-radius: 50%; animation: spin 1s linear infinite; margin-left: 5px;"></div>';
    buttonElement.disabled = true;

    // Запускаем проверку почты и обновление
    checkNewEmailsAndReload(leadId, dialogId);

    // Восстанавливаем кнопку через 2 секунды
    setTimeout(() => {
        buttonElement.innerHTML = originalHtml;
        buttonElement.disabled = false;
    }, 2000);
}


// ✅ ОБНОВЛЕННАЯ ФУНКЦИЯ ЗАГРУЗКИ ИСТОРИИ (С ФИЛ// Функция для загрузки истории сообщений с файламиЬТРОМ ВХОДЯЩИХ)


// ✅ ОБНОВЛЕННАЯ ФУНКЦИЯ ЗАГРУЗКИ ИСТОРИИ (С ЗАГРУЗКОЙ НАСТРОЕК С СЕРВЕРА)
function loadMessagesHistory(leadId, dialogId, isSilent = false) {
    console.log('📨 Загрузка истории для диалога:', dialogId, isSilent ? '(тихая)' : '');

    const contentDiv = document.querySelector('.messages-history-content');
    if (!contentDiv) return;

    //  СОХРАНЯЕМ ПОЗИЦИЮ ПРОКРУТКИ ПЕРЕД ОБНОВЛЕНИЕМ
    const scrollContainer = contentDiv.querySelector('.messages-scroll-container');
    let scrollPosition = 0;
    if (scrollContainer) {
        scrollPosition = scrollContainer.scrollTop;
    }

    //  ПОКАЗЫВАЕМ ИНДИКАТОР ТОЛЬКО ПРИ РУЧНОЙ ЗАГРУЗКЕ
    if (!isSilent && !contentDiv.querySelector('.message-item')) {
        contentDiv.innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <div class="spinner" style="border: 3px solid #f3f3f3; border-top: 3px solid #007cba; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px;"></div>
                <p>Загрузка истории сообщений...</p>
            </div>
        `;
    }

    const timestamp = new Date().getTime();

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'get_dialog_messages',
            dialog_id: dialogId,
            files_enabled: window.messagesHistoryEnabled ? '1' : '0',
            nonce: crm_ajax.nonce,
            _t: timestamp
        },
        success: function (response) {
            if (response.success) {
                renderMessagesHistory(response.data, contentDiv, leadId, dialogId);

                //  ВОССТАНАВЛИВАЕМ ПОЗИЦИЮ ПРОКРУТКИ ПОСЛЕ ОБНОВЛЕНИЯ
                if (scrollContainer && isSilent) {
                    setTimeout(() => {
                        const newScrollContainer = contentDiv.querySelector('.messages-scroll-container');
                        if (newScrollContainer) {
                            newScrollContainer.scrollTop = scrollPosition;
                        }
                    }, 100);
                }
            }
        },
        error: function (xhr, status, error) {
            console.error('❌ AJAX ошибка:', error);
            if (!isSilent) {
                contentDiv.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <div style="font-size: 48px;">❌</div>
                        <p>Ошибка загрузки истории сообщений</p>
                    </div>
                `;
            }
        }
    });
}

// ✅ ФУНКЦИЯ ЗАГРУЗКИ НАСТРОЕК ФАЙЛОВ С СЕРВЕРА
function loadFileSettingsFromServer(dialogId) {
    return new Promise((resolve) => {
        // Сначала пробуем загрузить из localStorage
        const savedSetting = localStorage.getItem('messagesHistoryEnabled');
        if (savedSetting !== null) {
            window.messagesHistoryEnabled = savedSetting === 'true';
            console.log('📁 Настройка файлов из localStorage:', window.messagesHistoryEnabled);
            resolve();
            return;
        }

        // Если в localStorage нет, загружаем с сервера
        $.ajax({
            url: crm_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_file_setting',
                dialog_id: dialogId,
                nonce: crm_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    window.messagesHistoryEnabled = response.data.files_enabled;
                    localStorage.setItem('messagesHistoryEnabled', response.data.files_enabled.toString());
                    console.log('📁 Настройка файлов с сервера:', window.messagesHistoryEnabled);
                } else {
                    // По умолчанию включено
                    window.messagesHistoryEnabled = true;
                    console.log('📁 Настройка файлов по умолчанию:', window.messagesHistoryEnabled);
                }
                resolve();
            },
            error: function () {
                // При ошибке используем значение по умолчанию
                window.messagesHistoryEnabled = true;
                console.log('📁 Настройка файлов (ошибка загрузки, по умолчанию):', window.messagesHistoryEnabled);
                resolve();
            }
        });
    });
}

// ✅ ФУНКЦИЯ ДЛЯ ПРОВЕРКИ НОВЫХ СООБЩЕНИЙ
function isRecentMessage(sent_at) {
    if (!sent_at) return false;

    const messageTime = new Date(sent_at);
    const now = new Date();
    const diffMinutes = (now - messageTime) / (1000 * 60); // разница в минутах

    // Считаем сообщение новым, если оно получено в последние 10 минут
    return diffMinutes <= 10;
}

// ✅ ФУНКЦИЯ ДЛЯ ФОРМАТИРОВАНИЯ HTML
function formatMessageTextWithHTML(text) {
    if (!text) return '';

    // Если текст содержит HTML теги (письмо с картинками)
    if (text.includes('<') && text.includes('>')) {
        // Безопасно отображаем HTML (ограниченный набор тегов)
        const safeHTML = text
            .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '') // Удаляем скрипты
            .replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, '') // Удаляем стили
            .replace(/<img[^>]+src="([^"]+)"[^>]*>/gi, '<img src="$1" style="max-width: 100%; height: auto; border-radius: 4px; margin: 5px 0;" loading="lazy">') // Безопасные картинки
            .replace(/<a[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>/gi, '<a href="$1" target="_blank" style="color: #007cba; text-decoration: underline;">$2</a>') // Безопасные ссылки
            .replace(/<br\s*\/?>/gi, '<br>') // Переносы строк
            .replace(/<p[^>]*>/gi, '<p style="margin: 8px 0;">') // Параграфы
            .replace(/<div[^>]*>/gi, '<div style="margin: 5px 0;">'); // Дивы

        return safeHTML;
    } else {
        // Обычный текст
        return formatMessageText(text);
    }
}

// ✅ ФУНКЦИЯ ДЛЯ ФОРМАТИРОВАНИЯ ТЕКСТА
function formatMessageText(text) {
    if (!text) return '';

    // Заменяем переносы строк на <br>
    let formatted = text.replace(/\n/g, '<br>');

    // Оборачиваем ссылки в теги <a>
    formatted = formatted.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" style="color: #007cba; text-decoration: underline;">$1</a>');

    return formatted;
}

// ✅ ФУНКЦИЯ ДЛЯ ОТОБРАЖЕНИЯ ВРЕМЕНИ
function displayMoscowTime(dateString) {
    if (!dateString) return 'Дата не указана';

    try {
        const date = new Date(dateString);

        // Форматируем напрямую в Московском времени
        const options = {
            timeZone: 'Europe/Moscow',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };

        return new Intl.DateTimeFormat('ru-RU', options).format(date);
    } catch (error) {
        console.error('❌ Ошибка форматирования времени:', error);
        return dateString;
    }
}

// ✅ ФУНКЦИЯ ДЛЯ ОПРЕДЕЛЕНИЯ ИКОНКИ ФАЙЛА
function getFileIcon(fileName) {
    if (!fileName) return '📄';

    const extension = fileName.split('.').pop().toLowerCase();

    const iconMap = {
        // Изображения
        'jpg': '🖼️', 'jpeg': '🖼️', 'png': '🖼️', 'gif': '🖼️', 'bmp': '🖼️', 'svg': '🖼️', 'webp': '🖼️',

        // PDF
        'pdf': '📄',

        // Документы
        'doc': '📝', 'docx': '📝', 'txt': '📝', 'rtf': '📝',

        // Таблицы
        'xls': '📊', 'xlsx': '📊', 'csv': '📊',

        // Презентации
        'ppt': '📽️', 'pptx': '📽️',

        // Архивы
        'zip': '📦', 'rar': '📦', '7z': '📦', 'tar': '📦', 'gz': '📦',

        // Другие
        'mp3': '🎵', 'wav': '🎵', 'mp4': '🎬', 'avi': '🎬', 'mov': '🎬'
    };

    return iconMap[extension] || '📄';
}

// ✅ ФУНКЦИЯ ДЛЯ УВЕДОМЛЕНИЙ
function showTempNotification(message, type = 'info') {
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        info: '#17a2b8'
    };

    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colors[type] || colors.info};
        color: white;
        padding: 12px 20px;
        border-radius: 4px;
        z-index: 10001;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        animation: slideIn 0.3s ease-out;
    `;

    notification.textContent = message;
    document.body.appendChild(notification);

    // Удаляем уведомление через 3 секунды
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// ✅ ОБРАБОТЧИК ДЛЯ СКАЧИВАНИЯ ВХОДЯЩИХ ФАЙЛОВ


function downloadIncomingAttachment(messageId, fileName, button) {
    console.log('📥 Скачивание входящего файла:', { messageId, fileName });

    // Показываем индикатор загрузки
    const originalText = button.innerHTML;
    button.innerHTML = '⏳...';
    button.disabled = true;

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'download_incoming_attachment',
            message_id: messageId,
            file_name: fileName,
            nonce: crm_ajax.nonce
        },
        xhrFields: {
            responseType: 'blob' // Важно для скачивания файлов
        },
        success: function (response, status, xhr) {
            // Создаем ссылку для скачивания
            const blob = new Blob([response]);
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = downloadUrl;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(downloadUrl);

            // Восстанавливаем кнопку
            button.innerHTML = originalText;
            button.disabled = false;

            console.log('✅ Файл скачан:', fileName);
        },
        error: function (xhr, status, error) {
            console.error('❌ Ошибка скачивания:', error);
            alert('Ошибка при скачивании файла');

            // Восстанавливаем кнопку
            button.innerHTML = originalText;
            button.disabled = false;
        }
    });
}


// ✅ ФУНКЦИЯ РЕНДЕРИНГА ИСТОРИИ СООБЩЕНИЙ
function renderMessagesHistory(messages, container, leadId, dialogId) {
    console.log('🎨 Рендерим сообщения:', messages);

    if (!messages || !Array.isArray(messages)) {
        console.error('❌ Ошибка: messages не является массивом', messages);
        container.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #666;">
                <div style="font-size: 48px;">❌</div>
                <p>Ошибка отображения истории</p>
            </div>
        `;
        return;
    }

    const sortedMessages = messages.sort((a, b) => new Date(b.sent_at) - new Date(a.sent_at));

    const outgoingCount = sortedMessages.filter(m => m.direction === 'outgoing').length;
    const incomingCount = sortedMessages.filter(m => m.direction === 'incoming').length;
    const filteredIncomingCount = window.messagesHistoryEnabled === false ? 0 : incomingCount;

    const newMessagesCount = sortedMessages.filter(msg =>
        msg.direction === 'incoming' && isRecentMessage(msg.sent_at)
    ).length;

    let messagesHtml = `
        <!-- ✅ БЛОК СТАТИСТИКИ С УЧЕТОМ ФИЛЬТРА -->
        <div class="stats-header" style="
            flex-shrink: 0;
            padding: 5px; 
            border-radius: 6px; 
            border-left: 4px solid #6c757d;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        ">
            <div>
                <small><strong>Статистика:</strong> Всего: ${sortedMessages.length} | Исходящие: ${outgoingCount} | Входящие: ${filteredIncomingCount}</small>
                ${window.messagesHistoryEnabled === false ? `<br><small style="color: #dc3545;line-height:22px;"><strong>Исходящие соообшения, приход входящих сообщений будут добавлены в будущих обновлениях</strong></small>` : ''}
                ${newMessagesCount > 0 && window.messagesHistoryEnabled !== false ? `<br><small style="color: #28a745;"><strong>Новых писем:</strong> ${newMessagesCount}</small>` : ''}
            </div>
        </div>

        <!-- ✅ ПРОКРУЧИВАЕМАЯ ОБЛАСТЬ СООБЩЕНИЙ -->
        <div class="messages-scroll-container" style="
            flex: 1;
            overflow-y: auto; 
            padding-right: 10px;
            max-height: 60vh;
        ">
    `;

    if (sortedMessages.length === 0) {
        messagesHtml += `
            <div style="text-align: center; padding: 40px; color: #666;">
                <div style="font-size: 48px;">📭</div>
                <p>Нет сообщений в этом диалоге</p>}
            </div>
        `;
    } else {
        sortedMessages.forEach(function (message, index) {
            const isOutgoing = message.direction === 'outgoing';
            const messageDate = displayMoscowTime(message.sent_at);
            const messageText = message.message || 'Сообщение отсутствует';
            const senderEmail = message.sender_email || 'Email не указан';

            const recipientEmail = isOutgoing ?
                (message.email || message.recipient_email || 'Email не указан') :
                message.sender_email;

            const displayEmail = isOutgoing ?
                `📤 от ${senderEmail}  →  ${recipientEmail}` :
                `📥 от ${recipientEmail}  →  ${senderEmail}`;

            messagesHtml += `
                <div class="message-item" style="
                    border: 1px solid ${isOutgoing ? '#d1ecf1' : '#f8d7da'};
                    border-radius: 8px;
                    border-left: 4px solid ${isOutgoing ? '#007cba' : '#28a745'};
                ">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                        <div style="flex: 1;">
                            <div style="font-weight: bold; color: #333; margin-bottom: 5px;">
                                ${displayEmail}
                            </div>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <span style="padding: 2px 8px; background: ${isOutgoing ? '#e7f3ff' : '#e7f7ed'}; color: ${isOutgoing ? '#0066cc' : '#155724'}; border-radius: 12px; font-size: 11px;">
                                    ${isOutgoing ? '📤 Исходящее' : '📥 Входящее'}
                                </span>
                                ${isRecentMessage(message.sent_at) && message.direction === 'incoming' ? `
                                    <span style="padding: 2px 8px; background: #d4edda; color: #155724; border-radius: 12px; font-size: 11px;">
                                        🔔 Новое
                                    </span>
                                ` : ''}
                                ${message.has_images ? `
                                    <span style="padding: 2px 8px; background: #fff3cd; color: #856404; border-radius: 12px; font-size: 11px;">
                                        🖼️ С картинками
                                    </span>
                                ` : ''}
                            </div>
                        </div>
                        <small style="color: #666; margin-left: 10px; text-align: right;">
                            ${messageDate}
                            <br><span style="font-size: 9px; color: #999;">МСК</span>
                        </small>
                    </div>

                    ${message.subject ? `
                        <div style="margin-bottom: 8px; padding: 8px;  border-radius: 4px; border-left: 3px solid #6c757d;">
                            <strong>Тема:</strong> ${message.subject}
                        </div>
                    ` : ''}

                    <div style=
                        "border-radius: 6px; 
                        padding-left: 5px;
                        border: 1px solid #eee;
                        margin-bottom: 10px;
                        line-height: 1.5;
                        word-wrap: break-word;
                    ">
                        ${formatMessageTextWithHTML(messageText)}
                    </div>
            `;

            // БЛОК ДЛЯ ВЛОЖЕНИЙ
            messagesHtml += `
                <div style="margin-top: 10px;">
                    <strong style="display: block; margin-bottom: 8px; color: #555; font-size: 13px;">
                        📎 Прикрепленные файлы:
                    </strong>
            `;

            if (message.attachments && message.attachments.length > 0) {
                messagesHtml += `<div style="display: flex; flex-wrap: wrap; gap: 8px;">`;

                message.attachments.forEach(function (attachment) {
                    const fileIcon = getFileIcon(attachment.file_name || attachment.name);
                    const fileName = attachment.file_name || attachment.name || 'Файл';
                    const fileUrl = attachment.file_url || attachment.url;

                    if (fileUrl) {
                        // Вариант с проверкой WordPress переменных
const baseUrl = window.crm_ajax?.home_url || window.location.origin;
const directFileUrl = fileUrl.startsWith('http') ? fileUrl : `${baseUrl}${fileUrl}`;

                        messagesHtml += `
                            <div class="attachment-item server-attachment"
                                 style="
                                     display: flex;
                                     align-items: center;
                                     padding: 6px 10px;
                                     background: #a2b3c5ff;
                                     border: 1px solid #dee2e6;
                                     border-radius: 4px;
                                     font-size: 14px;
                                     gap: 6px;
                                 "
                                 data-file-url="${directFileUrl}"
                                 data-file-name="${fileName}">
                                <span class="attachment-icon">${fileIcon}</span>
                                <span class="attachment-name" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    ${fileName}
                                </span>
                                <a href="${directFileUrl}" target="_blank"
                                   style="
                                       background: #17a2b8;
                                       color: white;
                                       border: none;
                                       padding: 4px 12px;
                                       border-radius: 4px;
                                       font-size: 11px;
                                       cursor: pointer;
                                       text-decoration: none;
                                       display: inline-block;
                                   "
                                   title="Посмотреть файл">👁️ Посмотреть</a>
                            </div>
                        `;
                    } else if (message.direction === 'incoming') {
                        //  ПРОВЕРЯЕМ ЕСТЬ ЛИ ФАЙЛ В БД
                        const hasFileInDB = attachment.file_size && attachment.file_size !== "0" && attachment.file_size !== "0";

                        //  ЕСЛИ ГАЛКА ОТКЛЮЧЕНА И ФАЙЛА НЕТ В БД - ПРОПУСКАЕМ
                        if (window.messagesHistoryEnabled === false && !hasFileInDB) {
                            return; // не добавляем этот файл в HTML
                        }

                        messagesHtml += `
        <div class="attachment-item incoming-attachment"
             style="
                 display: flex;
                 align-items: center;
                 padding: 6px 10px;
                 background: ${hasFileInDB ? '#fff3cd' : '#f8d7da'};
                 border: 1px solid ${hasFileInDB ? '#ffeaa7' : '#f5c6cb'};
                 border-radius: 4px;
                 font-size: 14px;
                 gap: 6px;
             "
             data-message-id="${message.id}"
             data-file-name="${fileName}">
            <span class="attachment-icon">${fileIcon}</span>
            <span class="attachment-name" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                ${fileName}
            </span>
            ${hasFileInDB ? `
                <a href="${crm_ajax.ajaxurl}?action=download_incoming_attachment&message_id=${message.id}&file_name=${encodeURIComponent(fileName)}&nonce=${crm_ajax.nonce}&view=1"
                   target="_blank"
                   style="
                       background: #17a2b8;
                       color: white;
                       border: none;
                       padding: 4px 12px;
                       border-radius: 4px;
                       font-size: 11px;
                       cursor: pointer;
                       text-decoration: none;
                       display: inline-block;
                   "
                   title="Посмотреть файл">
                    👁️ Посмотреть
                </a>
            ` : `
                <span style="
                    background: #dc3545;
                    color: white;
                    border: none;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 10px;
                    cursor: not-allowed;
                "
                title="Файл не был загружен в базу данных">
                    ⚠️ Не загружен
                </span>
            `}
        </div>
    `;
                    } else {
                        // 🔥 ИСПОЛЬЗУЕМ ПРАВИЛЬНЫЙ ПУТЬ К ФАЙЛУ В ПАПКЕ ДИАЛОГА
                        const outgoingFileUrl = getOutgoingFileUrl(fileName, leadId, dialogId);

                        messagesHtml += `
        <div class="attachment-item outgoing-attachment"
             style="
                 display: flex;
                 align-items: center;
                 padding: 6px 10px;
                 background: #e7f3ff;
                 border: 1px solid #b3d7ff;
                 border-radius: 4px;
                 font-size: 14px;
                 gap: 6px;
             "
             data-file-url="${outgoingFileUrl}"
             data-file-name="${fileName}">
            <span class="attachment-icon">${fileIcon}</span>
            <span class="attachment-name" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                ${fileName}
            </span>
            <a href="${outgoingFileUrl}" target="_blank"
               style="
                   background: #17a2b8;
                   color: white;
                   border: none;
                   padding: 4px 12px;
                   border-radius: 4px;
                   font-size: 11px;
                   cursor: pointer;
                   text-decoration: none;
                   display: inline-block;
               "
               title="Посмотреть файл">
                👁️ Посмотреть
            </a>
        </div>
    `;
                    }
                });

                messagesHtml += `</div>`;
            } else {
                messagesHtml += `
                    <div style="
                        padding: 8px 12px;
                        border: 1px dashed #dee2e6;
                        border-radius: 4px;
                        color: #6c757d;
                        font-size: 14px;
                        text-align: center;
                    ">
                        Файлы не прикреплены
                    </div>
                `;
            }

            messagesHtml += `
                </div>
            </div>`;
        });
    }

    messagesHtml += `
        </div> <!-- закрываем messages-scroll-container -->
    `;

    container.innerHTML = messagesHtml;

    console.log('✅ Рендеринг завершен', {
        totalMessages: sortedMessages.length,
        incomingEnabled: window.messagesHistoryEnabled !== false
    });
}




// ✅ ОБНОВЛЕННАЯ ФУНКЦИЯ ЗАГРУЗКИ ИСТОРИИ
// ✅ ФУНКЦИЯ ЗАГРУЗКИ ИСТОРИИ (БЕЗ АНИМАЦИИ ПРИ АВТООБНОВЛЕНИИ)
function loadMessagesHistory(leadId, dialogId, isSilent = false) {
    console.log('📨 Загрузка истории для диалога:', dialogId, isSilent ? '(тихая)' : '');

    const contentDiv = document.querySelector('.messages-history-content');
    if (!contentDiv) return;

    //  ПОКАЗЫВАЕМ ИНДИКАТОР ТОЛЬКО ПРИ РУЧНОЙ ЗАГРУЗКЕ
    if (!isSilent && !contentDiv.querySelector('.message-item')) {
        contentDiv.innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <div class="spinner" style="border: 3px solid #f3f3f3; border-top: 3px solid #007cba; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px;"></div>
                <p>Загрузка истории сообщений...</p>
            </div>
        `;
    }

    const timestamp = new Date().getTime();

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'get_dialog_messages',
            dialog_id: dialogId,
            files_enabled: window.messagesHistoryEnabled ? '1' : '0',
            nonce: crm_ajax.nonce,
            _t: timestamp
        },
        success: function (response) {
            if (response.success) {
                renderMessagesHistory(response.data, contentDiv, leadId, dialogId);
            }
        },
        error: function (xhr, status, error) {
            console.error('❌ AJAX ошибка:', error);
            if (!isSilent) {
                contentDiv.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <div style="font-size: 48px;">❌</div>
                        <p>Ошибка загрузки истории сообщений</p>
                    </div>
                `;
            }
        }
    });
}

// ✅ ИНИЦИАЛИЗАЦИЯ ПРИ ЗАГРУЗКЕ СТРАНИЦЫ
// ✅ ОБНОВЛЕННАЯ ФУНКЦИЯ ИНИЦИАЛИЗАЦИИ
function loadMessagesHistorySettings() {
    // По умолчанию включено, но реальное значение загрузится при открытии диалога
    window.messagesHistoryEnabled = true;

    console.log('📨 Инициализация настроек истории сообщений');
}


// ✅ НОВАЯ ФУНКЦИЯ ДЛЯ ФОРМАТИРОВАНИЯ HTML
function formatMessageTextWithHTML(text) {
    if (!text) return '';

    // Если текст содержит HTML теги (письмо с картинками)
    if (text.includes('<') && text.includes('>')) {
        // Безопасно отображаем HTML (ограниченный набор тегов)
        const safeHTML = text
            .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '') // Удаляем скрипты
            .replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, '') // Удаляем стили
            .replace(/<img[^>]+src="([^"]+)"[^>]*>/gi, '<img src="$1" style="max-width: 100%; height: auto; border-radius: 4px; margin: 5px 0;" loading="lazy">') // Безопасные картинки
            .replace(/<a[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>/gi, '<a href="$1" target="_blank" style="color: #007cba; text-decoration: underline;">$2</a>') // Безопасные ссылки
            .replace(/<br\s*\/?>/gi, '<br>') // Переносы строк
            .replace(/<p[^>]*>/gi, '<p style="margin: 8px 0;">') // Параграфы
            .replace(/<div[^>]*>/gi, '<div style="margin: 5px 0;">'); // Дивы

        return safeHTML;
    } else {
        // Обычный текст
        return formatMessageText(text);
    }
}

// ✅ НОВАЯ ФУНКЦИЯ ДЛЯ УВЕДОМЛЕНИЙ
function showTempNotification(message, type = 'info') {
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        info: '#17a2b8'
    };

    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colors[type] || colors.info};
        color: white;
        padding: 12px 20px;
        border-radius: 4px;
        z-index: 10001;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        animation: slideIn 0.3s ease-out;
    `;

    notification.textContent = message;
    document.body.appendChild(notification);

    // Удаляем уведомление через 3 секунды
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Добавьте стили для анимации
const notificationStyles = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;

if (!document.querySelector('#notification-styles')) {
    const styleElement = document.createElement('style');
    styleElement.id = 'notification-styles';
    styleElement.textContent = notificationStyles;
    document.head.appendChild(styleElement);
}

// ✅ НОВАЯ ФУНКЦИЯ ДЛЯ КОНВЕРТАЦИИ ВРЕМЕНИ В МОСКОВСКОЕ
function convertToMoscowTime(dateString) {
    if (!dateString) return 'Дата не указана';

    try {
        // Создаем объект Date из строки
        const date = new Date(dateString);

        // Московское время UTC+3
        const moscowOffset = 3 * 60; // 3 часа в минутах

        // Получаем локальное время и добавляем смещение
        const localTime = date.getTime();
        const localOffset = date.getTimezoneOffset(); // разница в минутах между локальным временем и UTC
        const moscowTime = new Date(localTime + (localOffset + moscowOffset) * 60000);

        // Форматируем в русский формат
        const formattedDate = moscowTime.toLocaleString('ru-RU', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });

        return formattedDate;
    } catch (error) {
        console.error('❌ Ошибка конвертации времени:', error);
        return 'Ошибка даты';
    }
}

// ✅ АЛЬТЕРНАТИВНЫЙ ВАРИАНТ - ПРОСТАЯ КОНВЕРТАЦИЯ (если первый не работает)
// ✅ ИСПРАВЛЕННАЯ ФУНКЦИЯ КОНВЕРТАЦИИ ВРЕМЕНИ
function convertToMoscowTime(dateString) {
    if (!dateString) return 'Дата не указана';

    try {
        // Создаем объект Date из строки
        // Указываем что время УЖЕ в Московском часовом поясе
        const date = new Date(dateString + ' GMT+0300');

        // Форматируем в русский формат
        const formattedDate = date.toLocaleString('ru-RU', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });

        return formattedDate;
    } catch (error) {
        console.error('❌ Ошибка конвертации времени:', error);
        // Пробуем альтернативный метод
        return convertToMoscowTimeAlternative(dateString);
    }
}

// ✅ АЛЬТЕРНАТИВНЫЙ МЕТОД - ПРОСТО ИСПОЛЬЗУЕМ КАК ЕСТЬ
function convertToMoscowTimeAlternative(dateString) {
    if (!dateString) return 'Дата не указана';

    try {
        const date = new Date(dateString);

        // Просто форматируем без изменений - предполагаем что время уже правильное
        return date.toLocaleString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            timeZone: 'Europe/Moscow' // Явно указываем Московский часовой пояс
        });
    } catch (error) {
        console.error('❌ Ошибка альтернативной конвертации:', error);
        return dateString; // Возвращаем как есть
    }
}


// ✅ САМЫЙ ПРОСТОЙ МЕТОД - БЕЗ КОНВЕРТАЦИИ
function displayMoscowTime(dateString) {
    if (!dateString) return 'Дата не указана';

    try {
        const date = new Date(dateString);

        // Форматируем напрямую в Московском времени
        const options = {
            timeZone: 'Europe/Moscow',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };

        return new Intl.DateTimeFormat('ru-RU', options).format(date);
    } catch (error) {
        console.error('❌ Ошибка форматирования времени:', error);
        return dateString;
    }
}

// Функция для запуска debug тестов
function runDebugTests(leadId, dialogId) {
    console.group('🔧 DEBUG TESTS');
    console.log('🔍 Параметры:', { leadId, dialogId });

    // Тест 1: Проверка наличия crm_ajax
    console.log('1. crm_ajax объект:', crm_ajax ? '✅ Найден' : '❌ Не найден');
    if (crm_ajax) {
        console.log('   - ajaxurl:', crm_ajax.ajaxurl);
        console.log('   - nonce:', crm_ajax.nonce ? '✅ Есть' : '❌ Нет');
    }

    // Тест 2: Проверка таблицы через диагностику
    console.log('2. Проверяем таблицу сообщений...');

    fetch(crm_ajax.ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'debug_messages_table',
            'dialog_id': dialogId,
            'nonce': crm_ajax.nonce
        })
    })
        .then(response => {
            console.log('   - HTTP статус:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('   - Диагностика таблицы:', data);
            console.log('   - Таблица существует:', data.success ? '✅' : '❌');
            if (data.success) {
                console.log('   - Столбцы:', data.data.columns);
                console.log('   - Всего сообщений:', data.data.total_messages);
                console.log('   - Сообщений в диалоге:', data.data.dialog_messages?.length || 0);

                // Логируем сообщения диалога для отладки
                if (data.data.dialog_messages) {
                    console.log('   - Сообщения диалога:');
                    data.data.dialog_messages.forEach(msg => {
                        console.log(`     - ID: ${msg.id}, Direction: ${msg.direction}, From: ${msg.sender_email}, Date: ${msg.sent_at}`);
                    });
                }
            }
        })
        .catch(error => {
            console.error('   - Ошибка диагностики:', error);
        });

    // Тест 3: Упрощенный тест AJAX
    console.log('3. Тестируем AJAX запрос...');

    const testData = new URLSearchParams({
        'action': 'get_dialog_messages',
        'dialog_id': dialogId,
        'nonce': crm_ajax.nonce
    });

    fetch(crm_ajax.ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: testData
    })
        .then(response => {
            console.log('   - HTTP статус:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('   - Сырой ответ:', text);
            try {
                const data = JSON.parse(text);
                console.log('   - Парсинг JSON:', data);
            } catch (e) {
                console.error('   - Ошибка парсинга JSON:', e);
            }
        })
        .catch(error => {
            console.error('   - Ошибка fetch:', error);
        });

    console.groupEnd();

    // Показываем результаты в модальном окне
    showDebugResults(leadId, dialogId);
}

// ✅ УЛУЧШЕННАЯ ФУНКЦИЯ ОТОБРАЖЕНИЯ ВРЕМЕНИ С ИНФОРМАЦИЕЙ О ЧАСОВОМ ПОЯСЕ
function displayMessageTime(message) {
    if (!message.sent_at) return 'Дата не указана';

    try {
        // Если есть информация о часовом поясе, используем ее
        if (message.timezone_info) {
            const tzInfo = typeof message.timezone_info === 'string'
                ? JSON.parse(message.timezone_info)
                : message.timezone_info;

            const date = new Date(message.sent_at);
            const formattedDate = date.toLocaleString('ru-RU', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });

            return {
                date: formattedDate,
                timezone: tzInfo.timezone || 'МСК',
                original: tzInfo.original_date || '',
                offset: tzInfo.offset_hours || 0
            };
        }

        // Если нет информации о часовом поясе, предполагаем Московское время
        const date = new Date(message.sent_at);
        const formattedDate = date.toLocaleString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            timeZone: 'Europe/Moscow'
        });

        return {
            date: formattedDate,
            timezone: 'МСК',
            original: '',
            offset: 3
        };

    } catch (error) {
        console.error('❌ Ошибка форматирования времени:', error);
        return {
            date: message.sent_at,
            timezone: 'Ошибка',
            original: '',
            offset: 0
        };
    }
}


// Функция для показа результатов debug
function showDebugResults(leadId, dialogId) {
    const contentDiv = document.querySelector('.messages-history-content');
    contentDiv.innerHTML = `
        <div style="padding: 20px;">
            <h4 style="color: #333; margin-bottom: 15px;">🔧 Debug Результаты</h4>
            <div style="padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                <p><strong>Lead ID:</strong> ${leadId}</p>
                <p><strong>Dialog ID:</strong> ${dialogId}</p>
                <p><strong>AJAX URL:</strong> ${crm_ajax?.ajaxurl || 'Не найден'}</p>
                <p><strong>Nonce:</strong> ${crm_ajax?.nonce ? '✅ Есть' : '❌ Нет'}</p>
            </div>
            <p>📊 Проверьте консоль браузера (F12) для подробной информации</p>
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button onclick="loadMessagesHistory('${leadId}', '${dialogId}')"
                        style="padding: 8px 16px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    ↻ Обновить историю
                </button>
            </div>
        </div>
    `;
}

// ✅ ФУНКЦИЯ ДЛЯ АВТОМАТИЧЕСКОГО ОБНОВЛЕНИЯ ИСТОРИИ
// ✅ УЛУЧШЕННАЯ ФУНКЦИЯ АВТООБНОВЛЕНИЯ
// ✅ ИСПРАВЛЕННАЯ ФУНКЦИЯ АВТООБНОВЛЕНИЯ
// ✅ УПРОЩЕННАЯ ФУНКЦИЯ АВТООБНОВЛЕНИЯ
function startAutoRefresh(leadId, dialogId) {
    // Останавливаем предыдущий интервал если есть
    if (window.autoRefreshInterval) {
        clearInterval(window.autoRefreshInterval);
    }

  
}



// ✅ ФУНКЦИЯ ДЛЯ АВТОПРОВЕРКИ (неброское уведомление)
function showAutoCheckNotification(message, type = 'info') {
    // Создаем subtle уведомление в самом модальном окне
    const modal = document.querySelector('.messages-history-modal');
    if (!modal) return;

    const existingNotification = modal.querySelector('.auto-check-notification');
    if (existingNotification) existingNotification.remove();

    const colors = {
        success: '#d4edda',
        error: '#f8d7da',
        info: '#e7f3ff'
    };

    const notification = document.createElement('div');
    notification.className = 'auto-check-notification';
    notification.style.cssText = `
        position: sticky;
        top: 0;
        background: ${colors[type] || colors.info};
        color: ${type === 'success' ? '#155724' : type === 'error' ? '#721c24' : '#0066cc'};
        padding: 8px 12px;
        border-radius: 4px;
        margin-bottom: 10px;
        font-size: 14px;
        z-index: 100;
        border-left: 3px solid ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#007cba'};
    `;

    notification.innerHTML = `
        <span>${message}</span>
        <small style="float: right; opacity: 0.7;">${new Date().toLocaleTimeString('ru-RU')}</small>
    `;

   
}
// ✅ ОСТАНОВКА АВТООБНОВЛЕНИЯ ПРИ ЗАКРЫТИИ
function closeMessagesHistory() {
    const modal = document.querySelector('.messages-history-modal');
    const overlay = document.querySelector('.modal-overlay');

    if (modal) modal.remove();
    if (overlay) overlay.remove();



    // Убираем обработчик ESC
    document.removeEventListener('keydown', handleEscapeKey);
}


// Функция для определения иконки файла по расширению


// Функция для форматирования текста сообщения


// Функция для закрытия модального окна истории
function closeMessagesHistory() {
    const modal = document.querySelector('.messages-history-modal');
    const overlay = document.querySelector('.modal-overlay');

    if (modal) modal.remove();
    if (overlay) overlay.remove();

    // Убираем обработчик ESC
    document.removeEventListener('keydown', handleEscapeKey);
}

// Обработчик клавиши ESC
function handleEscapeKey(event) {
    if (event.key === 'Escape') {
        closeMessagesHistory();
    }
}

// Функция для обновления истории в реальном времени
function refreshMessagesHistory(leadId, dialogId) {
    if (document.querySelector('.messages-history-modal')) {
        console.log('🔄 Обновление истории...');
        loadMessagesHistory(leadId, dialogId);
    }
}

// ==================== ИНИЦИАЛИЗАЦИЯ ====================

// Добавляем стили для улучшенного внешнего вида
const historyStyles = `
    .messages-history-modal::-webkit-scrollbar {
        width: 8px;
    }
    .messages-history-modal::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    .messages-history-modal::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }
    .messages-history-modal::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    .view-attachment:hover {
        opacity: 0.7;
    }
`;

// Добавляем стили в документ
if (!document.querySelector('#crm-history-styles')) {
    const styleElement = document.createElement('style');
    styleElement.id = 'crm-history-styles';
    styleElement.textContent = historyStyles;
    document.head.appendChild(styleElement);
}

// Глобальные функции для отладки
window.debugCRM = {
    testConnection: function (dialogId) {
        runDebugTests('test', dialogId);
    },
    forceReload: function (leadId, dialogId) {
        loadMessagesHistory(leadId, dialogId);
    },
    showAjaxInfo: function () {
        console.log('crm_ajax:', crm_ajax);
    }
};

console.log('✅ CRM History JS loaded with debug functions');
console.log('💡 Use debugCRM.testConnection(DIALOG_ID) for testing');

