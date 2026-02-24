

document.addEventListener('DOMContentLoaded', function () {
    console.log('CRM Settings loaded - simplified version');

    const addButton = document.querySelector('.dobav_login');
    const loginList = document.querySelector('.login_spisok');
    const hostForm = document.getElementById('host-form');

    // 🔧 Функция для показа уведомлений
    function showNotification(message, type = 'success') {
        console.log(`Notification: ${type} - ${message}`);

        // Удаляем старые уведомления
        const oldNotices = document.querySelectorAll('.ajax-notice');
        oldNotices.forEach(notice => notice.remove());

        // Определяем иконку
        let icon = '✅';
        if (type === 'error') icon = '❌';
        if (type === 'info') icon = 'ℹ️';
        if (type === 'warning') icon = '⚠️';

        // Определяем цвета
        let bgColor, textColor, borderColor;

        if (type === 'success') {
            bgColor = '#d4edda';
            textColor = '#155724';
            borderColor = '#c3e6cb';
        } else if (type === 'error') {
            bgColor = '#f8d7da';
            textColor = '#721c24';
            borderColor = '#f5c6cb';
        } else if (type === 'info') {
            bgColor = '#cce5ff';
            textColor = '#004085';
            borderColor = '#b8daff';
        } else if (type === 'warning') {
            bgColor = '#fff3cd';
            textColor = '#856404';
            borderColor = '#ffeaa7';
        }

        const notice = document.createElement('div');
        notice.className = `ajax-notice notice notice-${type}`;
        notice.style.cssText = `
        margin: 20px 0;
        padding: 15px;
        background: ${bgColor};
        color: ${textColor};
        border: 1px solid ${borderColor};
        border-radius: 4px;
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 400px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    `;

        notice.innerHTML = `
        <p style="margin: 0; font-size: 16px; font-weight: bold;">
            ${icon} ${message}
        </p>
    `;

        document.body.appendChild(notice);

        // Автоматическое скрытие
        setTimeout(() => {
            notice.style.transition = 'opacity 0.5s ease';
            notice.style.opacity = '0';
            setTimeout(() => notice.remove(), 500);
        }, type === 'error' || type === 'warning' ? 8000 : 5000);
    }

    // 🔧 1. ДОБАВЛЕНИЕ НОВОГО ПОЛЯ (как было раньше)
    if (addButton) {
        addButton.addEventListener('click', function (e) {
            e.preventDefault();
            console.log('Add button clicked');

            const newItem = document.createElement('li');
            newItem.className = 'login_item';

            // Проверяем состояние галочки
            const isActive = document.querySelector('.wrap_heck input[name="host"]')?.checked || false;
            const mainHost = document.getElementById('smtp_host')?.value || '';

            newItem.innerHTML = `
                <div class="login_input">
                    <div class="login_wrap">
                        <label>Почта</label>
                        <input type="email" name="email[]" placeholder="ваша почта" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                    </div>
                    <div class="login_wrap">
                        <label>Пароль</label>
                        <input type="password" name="password[]" placeholder="пароль" required>
                    </div>
                    <div class="login_wrap_host" style="${isActive ? 'display: none;' : ''}">
                        <label>Хост</label>
                        <input type="text" name="host[]" value="${isActive ? mainHost : ''}" 
                            placeholder="введите SMTP хост" ${isActive ? '' : 'required'}>
                    </div>
                </div>
                <button type="button" class="update_login">добавить /<br> Изменить</button>
                <button type="button" class="remove_login_new">удалить</button>
            `;

            if (loginList) {
                loginList.appendChild(newItem);
                console.log('New email field added');
            }
        });
    }


    // 🔧 2. СОХРАНЕНИЕ ВСЕХ ПОЧТ (имитация отправки формы)
    function saveAllEmails() {
        console.log('Saving all emails...');

        // Создаем скрытую форму для отправки
        const tempForm = document.createElement('form');
        tempForm.method = 'POST';
        tempForm.style.display = 'none';

        // Получаем все данные
        const emailInputs = document.querySelectorAll('input[name="email[]"]');
        const passwordInputs = document.querySelectorAll('input[name="password[]"]');
        const hostInputs = document.querySelectorAll('input[name="host[]"]');

        let hasData = false;

        emailInputs.forEach((input, index) => {
            const email = input.value.trim();
            const password = passwordInputs[index]?.value || '';
            const host = hostInputs[index]?.value || '';

            if (email && password) {
                hasData = true;

                // Добавляем скрытые поля
                const emailField = document.createElement('input');
                emailField.type = 'hidden';
                emailField.name = 'email[]';
                emailField.value = email;
                tempForm.appendChild(emailField);

                const passwordField = document.createElement('input');
                passwordField.type = 'hidden';
                passwordField.name = 'password[]';
                passwordField.value = password;
                tempForm.appendChild(passwordField);

                const hostField = document.createElement('input');
                hostField.type = 'hidden';
                hostField.name = 'host[]';
                hostField.value = host;
                tempForm.appendChild(hostField);
            }
        });

        if (!hasData) {
            showNotification('Нет данных для сохранения', 'error');
            return;
        }

        document.body.appendChild(tempForm);

        // Показываем индикатор загрузки
        showNotification('Сохранение...', 'info');

        // Отправляем AJAX запрос
        fetch(window.location.href, {
            method: 'POST',
            body: new FormData(tempForm)
        })
            .then(response => response.text())
            .then(html => {
                console.log('Data saved successfully');
                showNotification('Данные сохранены успешно!');

                // Обновляем кнопки удаления
                updateDeleteButtons();


            })
            .catch(error => {
                console.error('Save error:', error);
                showNotification('Ошибка сохранения', 'error');
            })
            .finally(() => {
                document.body.removeChild(tempForm);
            });
    }

    // 🔧 3. ОБНОВЛЕНИЕ КНОПОК УДАЛЕНИЯ
    function updateDeleteButtons() {
        const items = document.querySelectorAll('.login_item');
        items.forEach((item, index) => {
            const emailInput = item.querySelector('input[name="email[]"]');
            const email = emailInput?.value.trim() || '';

            if (email && index > 0) { // Первую почту нельзя удалять
                const deleteBtn = item.querySelector('.remove_login, .remove_login_new');
                if (deleteBtn) {
                    deleteBtn.className = 'remove_login';
                    deleteBtn.dataset.email = email;
                }
            }
        });
    }

    // 🔧 4. AJAX ОБРАБОТКА ФОРМЫ ХОСТА (без nonce)
    if (hostForm) {
        hostForm.addEventListener('submit', function (e) {
            e.preventDefault();
            console.log('Host form submitted');

            const hostInput = document.getElementById('smtp_host');
            const host = hostInput.value.trim();

            if (!host) {
                showNotification('Введите SMTP хост', 'error');
                return;
            }

            const submitBtn = this.querySelector('.update_host');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Сохранение...';
            submitBtn.disabled = true;

            // Простой AJAX запрос без nonce
            const formData = new URLSearchParams();
            formData.append('update_host_action', '1');
            formData.append('smtp_host', host);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
                .then(response => response.text())
                .then(html => {
                    console.log('Host saved successfully');
                    showNotification('Хост успешно обновлен');

                    // Обновляем значения в полях хоста
                    document.querySelectorAll('input[name="host[]"]').forEach(input => {
                        input.value = host;
                    });
                })
                .catch(error => {
                    console.error('Host Error:', error);
                    showNotification('Ошибка сохранения хоста', 'error');
                })
                .finally(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                });
        });
    }

    // 🔧 5. ОБРАБОТКА ЧЕКБОКСА (как было раньше, но через AJAX)
    const checkbox = document.getElementById('active_checkbox');
    if (checkbox) {
        checkbox.addEventListener('change', function () {
            const isChecked = this.checked;
            const activeStatus = isChecked ? 1 : 0;

            console.log('Checkbox changed:', activeStatus);

            // Показываем/скрываем поля хоста
            const hostGlav = document.querySelector('.host_glav');
            const hostFields = document.querySelectorAll('.login_wrap_host');

            if (hostGlav) {
                hostGlav.style.display = isChecked ? 'flex' : 'none';
            }

            hostFields.forEach(field => {
                field.style.display = isChecked ? 'none' : 'flex';
            });

            // Обновляем required атрибуты
            document.querySelectorAll('input[name="host[]"]').forEach(input => {
                if (isChecked) {
                    input.removeAttribute('required');
                } else {
                    input.setAttribute('required', 'required');
                }
            });

            // AJAX запрос (как было в старом коде, но без перезагрузки)
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'update_active_status',
                    'active': activeStatus
                })
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Active status response:', data);

                    if (!data.success) {
                        this.checked = !isChecked;
                        showNotification('Ошибка сохранения настройки', 'error');
                    } else {
                        showNotification('Настройка сохранена');

                        // Копируем хости при переключении (как было в старом коде)
                        if (isChecked) {
                            const commonHost = document.getElementById('smtp_host').value;
                            document.querySelectorAll('input[name="host[]"]').forEach(input => {
                                input.value = commonHost || '';
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Active Error:', error);
                    this.checked = !isChecked;
                    showNotification('Ошибка соединения', 'error');
                });
        });
    }

    // 🔧 6. ДЕЛЕГИРОВАНИЕ СОБЫТИЙ ДЛЯ КНОПОК
    if (loginList) {
        loginList.addEventListener('click', function (e) {
            // Сохранение всех почт
            if (e.target.classList.contains('update_login')) {
                e.preventDefault();

                // Находим текущий элемент
                const currentItem = e.target.closest('.login_item');
                if (!currentItem) return;

                // Находим email в текущем элементе
                const currentEmailInput = currentItem.querySelector('input[name="email[]"]');
                if (!currentEmailInput) return;

                const currentEmail = currentEmailInput.value.trim();
                if (!currentEmail) {
                    showNotification('Введите email', 'error');
                    return;
                }

                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(currentEmail)) {
                    showNotification('Введите корректный email', 'error');
                    currentEmailInput.style.border = '2px solid red';
                    const inputId = currentEmailInput.id || currentEmailInput.name;
                    localStorage.setItem(`lastValidEmail_${inputId}`, currentEmail);
                    setTimeout(() => {
                        currentEmailInput.style.border = '';
                    }, 3000);
                    return; // Не очищаем и не сохраняем
                }

                // Находим все email поля, кроме текущего
                const allEmailInputs = document.querySelectorAll('input[name="email[]"]');
                let hasDuplicate = false;

                allEmailInputs.forEach((input, index) => {
                    // Пропускаем текущий input
                    if (input === currentEmailInput) return;

                    const otherEmail = input.value.trim();
                    if (otherEmail.toLowerCase() === currentEmail.toLowerCase()) {
                        hasDuplicate = true;
                        // Подсвечиваем дублирующееся поле
                        input.style.border = '2px solid red';
                        setTimeout(() => {
                            input.style.border = '';
                        }, 3000);
                    }
                });

                // Если нашли дубликат - показываем ошибку
                if (hasDuplicate) {
                    showNotification('Такая почта уже существует!', 'error');
                    // Подсвечиваем текущее поле
                    currentEmailInput.style.border = '2px solid red';
                    setTimeout(() => {
                        currentEmailInput.style.border = '';
                    }, 3000);
                    return; // Не сохраняем
                }

                // Если нет дубликатов - сохраняем
                saveAllEmails();
            }

            // Удаление существующей почты
            else if (e.target.classList.contains('remove_login')) {
                e.preventDefault();

                const item = e.target.closest('.login_item');
                const email = e.target.dataset.email;
                const emailInput = item?.querySelector('input[name="email[]"]');
                const emailDisplay = emailInput?.value || '';

                if (email) {
                    if (confirm('Вы уверены, что хотите удалить почту: ' + emailDisplay + '?')) {
                        // AJAX удаление
                        fetch(ajaxurl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                'action': 'delete_email_account',
                                'email': email
                            })
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    showNotification('Почта удалена');
                                    item.remove();
                                } else {
                                    showNotification('Ошибка удаления', 'error');
                                }
                            })
                            .catch(error => {
                                console.error('Delete error:', error);
                                showNotification('Ошибка соединения', 'error');
                            });
                    }
                }
            }

            // Удаление нового поля
            else if (e.target.classList.contains('remove_login_new')) {
                e.preventDefault();
                const item = e.target.closest('.login_item');
                if (confirm('Удалить это поле?')) {
                    item.remove();
                }
            }
        });
    }

    console.log('CRM Settings initialization complete');

    // Автоматическое скрытие старых уведомлений через 5 секунд
    const notices = document.querySelectorAll('.notice');
    notices.forEach(function (notice) {
        setTimeout(function () {
            notice.style.transition = 'opacity 0.5s ease';
            notice.style.opacity = '0';
            setTimeout(function () {
                if (notice.parentNode) {
                    notice.remove();
                }
            }, 500);
        }, 5000);
    });

    // 2. шаблон письма
    const saveBtn = document.getElementById('save-template-btn');
    const agencyField = document.getElementById('agency-name-field');
    const agencyPodvalField = document.getElementById('agency-podval-field');
    const avatZag = document.querySelector('.avat_zag');
    const colorPicker = document.getElementById('color-picker');

    if (saveBtn && agencyField && agencyPodvalField && avatZag && colorPicker) {

        let originalName = agencyField.textContent.trim();

        function formatName(name) {
            return name.replace(/\b\w/g, function (char) {
                return char.toUpperCase();
            });
        }

        originalName = formatName(originalName);
        saveBtn.addEventListener('click', function () {

            let newName = agencyField.textContent.trim();
            const newPodval = agencyPodvalField.textContent.trim();
            const newColor = colorPicker.value; // Получаем цвет из палитры
            const ajaxUrl = saveBtn.getAttribute('data-ajax-url');

            const oldName = agencyField.textContent.trim();

            console.log('🔧 Debug: Clicked save button');
            console.log('🔧 Debug: New name:', newName);
            console.log('🔧 Debug: Original  name:', oldName);
            console.log('🔧 Debug: New podval:', newPodval);
            console.log('🔧 Debug: New color:', newColor);

            saveBtn.textContent = 'Сохранение...';
            saveBtn.disabled = true;

            fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'save_agency_data',
                    'agency_name': newName,
                    'agency_podval': newPodval,
                    'agency_color': newColor // Добавляем цвет
                })
            })
                .then(response => {
                    console.log('🔧 Debug: Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('🔧 Debug: Response data:', data);
                    if (data.success) {
                        const formattedName = newName.split(' ').map(word => {
                            if (word.length > 0) {
                                return word.charAt(0).toUpperCase() + word.slice(1);
                            }
                            return word;
                        }).join(' ');

                        avatZag.textContent = formattedName;

                        // Обновляем цвет в кнопке выбора цвета
                        const colorButtonSpan = document.querySelector('.color-picker-btn span');
                        if (colorButtonSpan) {
                            colorButtonSpan.style.background = newColor;
                        }

                        originalName = formatName(newName);

                        // 🔥 ОБНОВЛЯЕМ ЦВЕТ ССЫЛОК В ПРЕДПРОСМОТРЕ
                        const mesLinks = document.querySelectorAll('.mes_link');
                        mesLinks.forEach(link => {
                            link.style.color = newColor;
                        });

                        saveBtn.textContent = 'Сохранено!';
                        setTimeout(() => {
                            saveBtn.textContent = 'Сохранить';
                        }, 2000);
                    } else {
                        agencyField.textContent = originalName;
                        alert('Ошибка сохранения: ' + data.data);
                        saveBtn.textContent = 'Сохранить';
                    }
                    saveBtn.disabled = false;
                })
                .catch(error => {
                    console.error('🔧 Debug: Fetch error:', error);
                    agencyField.textContent = originalName;
                    alert('Ошибка сети');
                    saveBtn.textContent = 'Сохранить';
                    saveBtn.disabled = false;
                });
        });
    }
    colorPicker.addEventListener('input', function (e) {
        const newColor = e.target.value;

        const mesLinks = document.querySelectorAll('.mes_link');
        mesLinks.forEach(link => {
            link.style.color = newColor;
        });

        const colorButtonSpan = document.querySelector('.color-picker-btn span');
        if (colorButtonSpan) {
            colorButtonSpan.style.background = newColor;
        }
    });

    const checkbox1 = document.querySelector('input[class="shab_mes_cjeck"]');

    checkbox1.addEventListener('click', function () {
        const isActive = this.checked ? 1 : 0;
        const originalState = !this.checked;

        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'action': 'update_shablon_active',
                'active': isActive
            })
        })
            .then(response => response.json())
            .then(data => {
                console.log('Response:', data);

                if (data.success) {
                    // Используем вашу функцию showNotification
                    showNotification('Статус успешно обновлен', 'success');
                } else {
                    checkbox1.checked = originalState;
                    showNotification('Ошибка: ' + (data.message || 'Не удалось обновить статус'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                checkbox1.checked = originalState;
                showNotification('Ошибка соединения с сервером', 'error');
            });
    });


    $(document).on('click', '.generate-pdf-btn-shablon', function (e) {
        e.preventDefault();
        e.stopPropagation();

        console.log('🔍 Генерация PDF из шаблона');

        // Определяем AJAX параметры
        var ajaxUrl;
        var nonce;

        if (typeof crm_ajax !== 'undefined') {
            ajaxUrl = crm_ajax.ajaxurl;
            nonce = crm_ajax.nonce;
        } else {
            var $saveBtn = $(this).siblings('.shab_kp');
            if ($saveBtn.length > 0 && $saveBtn.data('ajax-url')) {
                ajaxUrl = $saveBtn.data('ajax-url');
                nonce = 'template_nonce';
            } else {
                ajaxUrl = '/wp-admin/admin-ajax.php';
                nonce = window.template_nonce || 'template_nonce';
            }
        }

        // Находим контейнер и редактор
        var $container = $(this).closest('.set_punkt, .kp_shablon_red');
        var $editor = $container.find('.file-content-editor');

        if ($container.length === 0 || $editor.length === 0) {
            alert('Ошибка: Не найден редактор шаблона');
            return;
        }

        // Получаем содержимое редактора
        var fileContent = $editor.html();
        if (!fileContent || fileContent.trim() === '' || fileContent === '<br>') {
            alert('Введите текст для документа PDF');
            return;
        }

        // 🔥 ФИКСИРОВАННОЕ ИМЯ ДЛЯ ШАБЛОНОВ
        var pdfFileName = 'шаблон КП.pdf';

        // Меняем состояние кнопки
        var $button = $(this);
        var originalText = $button.text();
        $button.text('Создание PDF...').prop('disabled', true);

        // 🔥 Находим ссылку для просмотра (она уже есть в HTML)
        var $viewLink = $button.next('.view-template-link');
        if ($viewLink.length === 0) {
            // Если ссылки нет - создаем рядом с кнопкой
            $viewLink = $('<a href="#" class="view-template-link" style="display: inline-block; margin-left: 10px; padding: 5px 10px; background: #cccccc; color: #666666; text-decoration: none; border-radius: 3px; font-size: 12px; cursor: not-allowed; opacity: 0.7;" onclick="return false;"><img draggable="false" role="img" class="emoji" alt="📄" src="https://s.w.org/images/core/emoji/16.0.1/svg/1f4c4.svg"> создайте шаблон</a>');
            $button.after($viewLink);
        }

        // AJAX запрос
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'generate_pdf_file',
                file_content: fileContent,
                custom_file_name: pdfFileName,
                is_template: true,
                nonce: nonce
            },
            success: function (response) {
                console.log('✅ Ответ сервера:', response);

                if (response.success && response.data.file_url) {
                    console.log('📄 Ссылка от сервера:', response.data.file_url);

                    // Проверяем путь
                    var isInTemplates = response.data.file_url.includes('/shablon/');
                    var isInOtMeny = response.data.file_url.includes('/от_меня/');

                    // Исправляем путь если нужно
                    var finalUrl = response.data.file_url;
                    if (isInOtMeny) {
                        finalUrl = response.data.file_url.replace('/от_меня/', '/shablon/');
                    }

                    // 🔥 АКТИВИРУЕМ ССЫЛКУ ДЛЯ ПРОСМОТРА
                    $viewLink
                        .attr('href', finalUrl)
                        .attr('target', '_blank')
                        .removeAttr('onclick')
                        .css({
                            'background': isInTemplates ? '#28a745' : '#ffc107',
                            'color': 'white',
                            'cursor': 'pointer',
                            'opacity': '1'
                        })
                        .html('<img draggable="false" role="img" class="emoji" alt="📄" src="https://s.w.org/images/core/emoji/16.0.1/svg/1f4c4.svg"> посмотреть шаблон КП');



                } else {
                    var errorMsg = 'Ошибка: ' + (response.data || 'неизвестная ошибка');
                    console.error('❌ Ошибка:', errorMsg);
                    alert(errorMsg);
                }
            },
            error: function (xhr, status, error) {
                console.error('❌ Ошибка AJAX:', error);
                alert('Ошибка сети: ' + error);
            },
            complete: function () {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });

    $(document).ready(function () {
        // Проверяем существование шаблона при загрузке страницы
        checkTemplateExists();

        function checkTemplateExists() {
            var $button = $('.generate-pdf-btn-shablon');
            if ($button.length === 0) return;

            var $viewLink = $button.next('.view-template-link');
            if ($viewLink.length === 0) {
                $viewLink = $('<a href="#" class="view-template-link" style="display: inline-block; margin-left: 10px; padding: 5px 10px; background: #cccccc; color: #666666; text-decoration: none; border-radius: 3px; font-size: 12px; cursor: not-allowed; opacity: 0.7;" onclick="return false;"><img draggable="false" role="img" class="emoji" alt="📄" src="https://s.w.org/images/core/emoji/16.0.1/svg/1f4c4.svg"> создайте шаблон</a>');
                $button.after($viewLink);
            }

            // URL предполагаемого шаблона
            var templateUrl = '/wp-content/uploads/crm_files/shablon/шаблон КП.pdf';

            // Проверяем, существует ли файл
            $.ajax({
                url: templateUrl,
                type: 'HEAD',
                success: function () {
                    // Файл существует - активируем ссылку
                    $viewLink
                        .attr('href', templateUrl)
                        .attr('target', '_blank')
                        .removeAttr('onclick')
                        .css({
                            'background': '#28a745',
                            'color': 'white',
                            'cursor': 'pointer',
                            'opacity': '1'
                        })
                        .html('<img draggable="false" role="img" class="emoji" alt="📄" src="https://s.w.org/images/core/emoji/16.0.1/svg/1f4c4.svg"> посмотреть шаблон КП');
                },
                error: function () {
                    // Файл не существует - оставляем неактивным
                    $viewLink
                        .attr('href', '#')
                        .attr('onclick', 'return false')
                        .css({
                            'background': '#cccccc',
                            'color': '#666666',
                            'cursor': 'not-allowed',
                            'opacity': '0.7'
                        })
                        .html('<img draggable="false" role="img" class="emoji" alt="📄" src="https://s.w.org/images/core/emoji/16.0.1/svg/1f4c4.svg"> создайте шаблон');
                }
            });
        }
    });


    jQuery(document).ready(function ($) {
        $('.red_dat_btn').hover(
            function () {
                // при наведении (mouseenter)
                $('.current-email').addClass('hov');
                $('.current-phone').addClass('hov');
            },
            function () {
                // при уходе (mouseleave) 
                $('.current-email').removeClass('hov');
                $('.current-phone').removeClass('hov');
            }
        );

        $('.red_dat_btn').click(function () {


            // Переключаем видимость red_dat
            $('.red_dat').fadeToggle(300);


        });


        $('.red_dat_tel_vibor').hover(
            function () {
                // при наведении (mouseenter)
                $('.current-phone').addClass('hov');
            },
            function () {
                // при уходе (mouseleave)
                $('.current-phone').removeClass('hov');
            }
        );

        $('.red_dat_mail_vibor ').hover(
            function () {
                // при наведении (mouseenter)
                $('.current-email').addClass('hov');
            },
            function () {
                // при уходе (mouseleave)
                $('.current-email').removeClass('hov');
            }
        );

        // Обработчик кнопок "выбрать"
        $(document).on('click', '.red_dat_tel_vibor, .red_dat_mail_vibor', function () {
            var type = $(this).data('type');
            var dataValue = $(this).data('value');
            var button = $(this);
            var value = '';

            // Проверяем, если data-value undefined или пустая строка
            if (dataValue === undefined || dataValue === '') {
                // Берем из input поля (ручной ввод)
                if (type == 'phone') {
                    value = $('#address_input_phone').val().trim();
                } else {
                    value = $('#address_input_email').val().trim();
                }
            } else {
                // Берем из data-value (кнопки из списка)
                value = dataValue;
            }

            // Если пусто, выходим
            if (!value) {
                alert('Введите значение');
                return;
            }

            // Проверка телефона, если это поле телефона и ввод ручной
            if (type == 'phone' && (dataValue === undefined || dataValue === '')) {
                var inputField = $('#address_input_phone');

                // Считаем количество цифр в строке
                var digitCount = (value.match(/\d/g) || []).length;

                if (digitCount !== 11) {
                    inputField.css('border', '2px solid red');
                    showNotification(' Введите корректный номер телефона (10 цифр после +7)', 'error');
                    return false;
                }

                // value остается неизменным!

                console.log('Телефон проверен, цифр:', digitCount, 'Значение:', value);
            }

            if (type == 'email' && (dataValue === undefined || dataValue === '')) {
                var emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                if (!emailPattern.test(value)) {
                    // Подсвечиваем поле
                    $('#address_input_email').css('border', '2px solid red');
                    // Показываем уведомление
                    showNotification('Введите корректный email латиницей (пример: example@mail.com)');
                    return false; // Прерываем выполнение
                }
            }

            console.log('Type:', type, 'Value:', value, 'DataValue:', dataValue);

            // Убираем зеленый у всех кнопок этого типа
            if (type == 'phone') {
                $('.red_dat_tel_vibor').css('background', '');
            } else {
                $('.red_dat_mail_vibor').css('background', '');
            }

            // Красим текущую кнопку в зеленый
            button.css('background', 'green');

            // Отправляем в базу
            $.ajax({
                url: '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'save_crm_contact',
                    type: type,
                    value: value
                },
                success: function (response) {
                    if (response.success) {
                        var selector = (type == 'phone') ? '.current-phone' : '.current-email';
                        var $element = $(selector);

                        $(selector)
                            .text(value)
                            .removeClass('no-selected')
                            .css({
                                'border': '3px solid green',
                                'border-radius': '10px',
                            });

                        // Убираем рамку через 3 секунды
                        setTimeout(function () {
                            $element.css({
                                'border': 'none',
                                'border-radius': '',
                            });
                        }, 3000);
                    }
                }

            });
        });

        $('#address_input_phone').on('input', function () {
            let value = $(this).val().replace(/\D/g, '');

            if (value.length > 1) {
                // Просто добавляем +7 в начало если его нет
                if (!value.startsWith('7') && !value.startsWith('8')) {
                    value = '7' + value;
                }

                // Базовая маска
                let formatted = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4, 7) + '-' + value.substring(7, 9) + '-' + value.substring(9, 11);
                $(this).val(formatted);
            }
        });

        // Валидация email при потере фокуса
        $('#address_input_email').on('blur', function () {
            var email = $(this).val().trim();
            // var emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

            // if (email && !emailPattern.test(email)) {
            //     $(this).css('border', '2px solid red');
            //     showNotification('Введите корректный email латиницей (пример: example@mail.com)');
            //     return false;
            // }

            $(this).css('border', '');
        });

        $('.red_style_btn').click(function () {


            // Переключаем видимость red_dat
            $('.red_style_wrap').fadeToggle(300);

            if ($('.red_style_wrap').is(':visible')) {
                initSlider();
            }

        });
        $(document).ready(function () {
            $('.scale').click(function (e) {
                e.preventDefault();



                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'apply_php_shadow'
                    },
                    success: function (response) {
                        if (response.success) {
                            // ОБЯЗАТЕЛЬНО ОБНОВЛЯЕМ ФОН!
                            const newUrl = response.data.url + '?v=' + Date.now();
                            $('.editor_red').css('background-image', 'url("' + newUrl + '")');

                            showNotification('Тень применена!');
                            console.log('Фон обновлен:', newUrl);
                        } else {
                            alert('Ошибка: ' + response.data);
                        }
                    },
                    error: function () {
                        alert('Ошибка сервера');
                    },
                    complete: function () {
                        $('#scale').text('Добавить тень').prop('disabled', false);
                    }
                });
            });
        });

        // Инициализируем сразу, если блок уже виден
        if ($('.red_style_wrap').is(':visible')) {
            initSlider();
        }
    });

    // редактирование менеджера
    jQuery(document).ready(function ($) {

        $('.red_name_men').click(function (e) {
            e.preventDefault();

            var $button = $(this);
            var $td = $button.closest('.avatar_name');
            var $nameDisplay = $td.find('.name-display');
            var newName = $nameDisplay.text().trim();
            var originalText = $button.text();

            // Проверяем есть ли ajaxurl
            if (typeof ajaxurl === 'undefined') {
                console.error('ajaxurl не определен!');
                return;
            }

            $button.text('Сохранение...').prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_name_in_db',
                    new_name: newName,
                    nonce: '<?php echo wp_create_nonce("update_name_nonce"); ?>'
                },
                success: function (response) {
                    if (response.success) {
                        // 🔥 УБИРАЕМ КЛАСС no-selected
                        $nameDisplay.removeClass('no-selected');

                        $button.text('Сохранено!');
                        setTimeout(function () {
                            $button.text(originalText).prop('disabled', false);
                        }, 1500);
                    } else {
                        // Используем безопасный вывод ошибки
                        console.error('Ошибка:', response.data);
                        // alert('Ошибка при сохранении');
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('Ошибка сети при сохранении');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
    });




    jQuery(document).ready(function ($) {


        $('.red_tel_men').click(function (e) {
            e.preventDefault();

            var $button = $(this);
            var $td = $button.closest('.avatar_tel');
            var $input = $td.find('.red_input_tel');
            var $display = $td.find('.tel-display'); // Находим элемент для отображения
            var newTel = $input.val().trim();
            var originalText = $button.text();

            // Проверяем есть ли ajaxurl
            if (typeof ajaxurl === 'undefined') {
                console.error('ajaxurl не определен!');
                return;
            }


            // Проверка телефона
            if (newTel) {
                // Считаем количество цифр в строке
                var digitCount = (newTel.match(/\d/g) || []).length;

                if (digitCount !== 11) {
                    $input.css('border', '2px solid red');
                    showNotification('Введите корректный номер телефона (10 цифр после +7)', 'error');
                    $button.text(originalText).prop('disabled', false);
                    return;
                }

                // Убираем красную рамку если была
                $input.css('border', '');
            }

            $button.text('Сохранение...').prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_tel_in_db',
                    new_tel: newTel
                },
                success: function (response) {
                    console.log('Response:', response);

                    if (response.success) {
                        // Обновляем текст в параграфе
                        var displayText = newTel || 'телефон не указан';
                        $display.text(displayText);

                        // 🔥 УБИРАЕМ КЛАСС no-selected С ОБОИХ ЭЛЕМЕНТОВ
                        $display.removeClass('no-selected');
                        $input.removeClass('no-selected');

                        $button.text('Сохранено!');
                        setTimeout(function () {
                            $button.text('Сменить').prop('disabled', false);
                        }, 1500);
                    } else {
                        alert('Ошибка: ' + (response.data || 'Неизвестная ошибка'));
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    alert('Ошибка сети. Проверьте консоль для деталей.');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });

        // Дополнительно: при вводе в input можно сразу обновлять параграф
        // (только визуально, до сохранения)
        $('.red_input_tel').on('input', function () {
            var $input = $(this);
            var $td = $input.closest('.avatar_tel');
            var $display = $td.find('.tel-display');
            $display.text($input.val() || 'телефон не указан');
            let value = $(this).val().replace(/\D/g, '');

            if (value.length > 1) {
                // Просто добавляем +7 в начало если его нет
                if (!value.startsWith('7') && !value.startsWith('8')) {
                    value = '7' + value;
                }

                // Базовая маска
                let formatted = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4, 7) + '-' + value.substring(7, 9) + '-' + value.substring(9, 11);
                $(this).val(formatted);
            }
        });


    });

    // стили шаблона кп
    jQuery(document).ready(function ($) {

        $('.background_wap').hover(
            function () {
                // при наведении (mouseenter)
                $('.editor_red').addClass('hov');
            },
            function () {
                // при уходе (mouseleave)
                $('.editor_red').removeClass('hov');
            }
        );

        $('.red_bacground').click(function (e) {
            e.preventDefault();

            var $button = $(this);
            var frame = wp.media({
                title: 'Выберите изображение',
                button: { text: 'Выбрать' },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();

                // Показываем загрузку
                $button.text('Сохраняем...').prop('disabled', true);

                // 1. Сначала отправляем на сервер
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'crm_save_background',
                        image_id: attachment.id
                    },
                    success: function (response) {
                        if (response.success) {
                            // 2. Только ПОСЛЕ успешного копирования меняем фон
                            $('.file-content-editor').css('background-image', 'url(' + response.data.url + ')');
                            $button.text('✓ Сохранено').css({
                                'background': '#4CAF50',
                                'color': 'white'
                            });
                        } else {
                            alert('Ошибка: ' + response.data);
                            $button.text('Ошибка').css('background', 'red');
                        }

                        setTimeout(() => {
                            $button.text('Смена картинки').prop('disabled', false).css({
                                'background': '',
                                'color': ''
                            });
                        }, 1500);
                    },
                    error: function () {
                        $button.text('Ошибка сети').prop('disabled', false);
                    }
                });
            });

            frame.open();
        });
    });

    jQuery(document).ready(function ($) {
        $('.red_logo').hover(
            function () {
                // при наведении (mouseenter)
                $('.logo_kp').addClass('hov');
            },
            function () {
                // при уходе (mouseleave)
                $('.logo_kp').removeClass('hov');
            }
        );

        $('.red_logo').click(function (e) {
            e.preventDefault();

            var $button = $(this);
            var frame = wp.media({
                title: 'Выберите логотип',
                button: { text: 'Выбрать' },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();

                $button.text('Сохраняем...').prop('disabled', true);

                // Отправляем на сервер
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'crm_save_logo',
                        image_id: attachment.id
                    },
                    success: function (response) {
                        if (response.success) {
                            // Меняем логотип на странице
                            $('.logo_kp').attr('src', response.data.url);
                            $button.text('✓ Сохранено').css({
                                'background': '#4CAF50',
                                'color': 'white'
                            });
                        } else {
                            alert('Ошибка: ' + response.data);
                            $button.text('Ошибка').css('background', 'red');
                        }

                        setTimeout(() => {
                            $button.text('смена логотипа').prop('disabled', false).css({
                                'background': '',
                                'color': ''
                            });
                        }, 1500);
                    },
                    error: function () {
                        $button.text('Ошибка сети').prop('disabled', false);
                    }
                });
            });

            frame.open();
        });
    });

    jQuery(document).ready(function ($) {

        $('.red_avatar').hover(
            function () {
                // при наведении (mouseenter)
                $('.avatar').addClass('hov');
            },
            function () {
                // при уходе (mouseleave)
                $('.avatar').removeClass('hov');
            }
        );

        $('.red_avatar').click(function (e) {
            e.preventDefault();

            var $button = $(this);
            var frame = wp.media({
                title: 'Выберите аватарку',
                button: { text: 'Выбрать' },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();

                $button.text('Сохраняем...').prop('disabled', true);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'crm_save_avatar',
                        image_id: attachment.id
                    },
                    success: function (response) {
                        if (response.success) {
                            // Меняем аватарку в элементе с классом .avatar
                            $('img.avatar').attr('src', response.data.url);
                            $button.text('✓ Сохранено').css({
                                'background': '#4CAF50',
                                'color': 'white'
                            });


                        } else {
                            alert('Ошибка: ' + response.data);
                            $button.text('Ошибка').css('background', 'red');
                        }

                        setTimeout(() => {
                            $button.text('смена логотипа').prop('disabled', false).css({
                                'background': '',
                                'color': ''
                            });
                        }, 1500);
                    },
                    error: function () {
                        $button.text('Ошибка сети').prop('disabled', false);
                    }
                });
            });

            frame.open();
        });
    });



    jQuery(document).ready(function ($) {
        // Функция преобразования rgb/rgba в HEX
        function rgbToHex(rgb) {
            // Если передали 'css' - проверяем CSS файл
            if (rgb === 'css') {
                console.log('🔍 Проверяем CSS файл на цвет закладки...');

                return fetch('/wp-content/uploads/crm_files/shablon/assets/css/style_kp.css')
                    .then(response => {
                        console.log('Статус ответа CSS:', response.status, response.ok ? 'OK' : 'ERROR');
                        if (!response.ok) {
                            console.log('❌ CSS файл не загружен');
                            return '#ffffff';
                        }
                        return response.text();
                    })
                    .then(css => {
                        console.log('✅ CSS файл загружен, длина:', css.length, 'символов');

                        if (!css) {
                            console.log('❌ CSS пустой');
                            return '#ffffff';
                        }

                        // Ищем цвет закладки
                        const regex = /\.zakladka::before\s*\{[^}]*background-color:\s*([^;!]+)/;
                        const match = css.match(regex);

                        if (match) {
                            const color = match[1].trim();
                            console.log('🎨 Найден цвет в CSS:', color);

                            // Конвертируем через rgbToHex
                            const hexColor = rgbToHex(color); // рекурсивный вызов
                            console.log('🔧 Конвертировано в HEX:', hexColor);

                            return hexColor;
                        } else {
                            console.log('ℹ️ Цвет закладки не найден в CSS');
                            console.log('Содержимое CSS (первые 500 символов):', css.substring(0, 500));
                            return '#ffffff';
                        }
                    })
                    .catch((error) => {
                        console.error('❌ Ошибка при загрузке CSS:', error);
                        return '#ffffff';
                    });
            }

            // Оригинальный код функции для конвертации цветов
            if (rgb.startsWith('#')) return rgb;

            var parts = rgb.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+\.?\d*))?\)$/);

            if (!parts) {
                if (rgb === 'black' || rgb === '#000' || rgb === '#000000') {
                    return '#000000';
                }
                return '#ffffff';
            }

            var r = parseInt(parts[1]).toString(16).padStart(2, '0');
            var g = parseInt(parts[2]).toString(16).padStart(2, '0');
            var b = parseInt(parts[3]).toString(16).padStart(2, '0');

            return '#' + r + g + b;
        }

        // 🔥 ИСПОЛЬЗОВАНИЕ:
        console.log('=== ЗАПУСК ПРОВЕРКИ ===');
        rgbToHex('css').then(color => {
            console.log('=== РЕЗУЛЬТАТ ===');
            console.log('Окончательный цвет:', color);

            if (color !== '#ffffff') {
                console.log('✅ Применяем цвет закладки');
                // Твой код для применения цвета...
            } else {
                console.log('ℹ️ Используем белый цвет (по умолчанию)');
            }
        });

        // Функция для определения текущего цвета .glav_color
        function getCurrentGlavColor() {
            var $sampleElement = $('.glav_color').first();
            if ($sampleElement.length > 0) {
                var computedColor = $sampleElement.css('color');
                if (computedColor && computedColor !== 'rgba(0, 0, 0, 0)') {
                    return rgbToHex(computedColor);
                }
            }
            return '#ffffff';
        }

        // Функция для определения текущего цвета второго цвета
        function getCurrentTwoColor() {
            var selectors = ['.two_color'];

            for (var i = 0; i < selectors.length; i++) {
                var $element = $(selectors[i]).first();
                if ($element.length > 0) {
                    var color = $element.css('color');
                    if (color && color !== 'rgba(0, 0, 0, 0)' && color !== 'transparent') {
                        return rgbToHex(color);
                    }
                }
            }

            return $('#kp_two_color').val() || '#000000';
        }

        // ДОБАВЛЯЕМ 6 функций для таблицы - ТОЧНО ТАК ЖЕ КАК ДЛЯ glav_color:
        // 1. Граница заголовков таблицы - ТОЧНО ТАК ЖЕ КАК glav_color
        function getCurrentCalcNameBord() {
            // Создаем временный элемент с нужным классом
            var $tempElement = $('<div class="table_cont_red" style="display: none;"></div>');
            $('body').append($tempElement);

            var computedBorder = $tempElement.css('border-top-color') || $tempElement.css('border-color');
            var result = computedBorder && computedBorder !== 'rgb(51, 51, 51)' ? rgbToHex(computedBorder) : '#ffffff';

            $tempElement.remove();
            return result;
        }

        // 2. Цвет текста заголовков таблицы - ТОЧНО ТАК ЖЕ
        function getCurrentKpCalcNameText() {
            var $tempElement = $('<div class="table_cont_red" style="display: none;"></div>');
            $('body').append($tempElement);

            var computedColor = $tempElement.css('color');
            console.log(computedColor, 'computedBg=')
            var result = computedColor && computedColor !== 'rgb(51, 51, 51)' ? rgbToHex(computedColor) : '#ffffff';


            $tempElement.remove();
            return result;
        }


        // 3. Фон "штуки и итоги" - ТОЧНО ТАК ЖЕ
        function getCurrentCalcNameShtBac() {
            var $tempElement = $('<div class="shtit_red" style="display: none;"></div>');
            $('body').append($tempElement);

            var computedBg = $tempElement.css('background-color');

            var result = computedBg && computedBg !== 'rgba(0, 0, 0, 0)' && computedBg !== 'transparent' ? rgbToHex(computedBg) : '#ffffff';

            $tempElement.remove();
            return result;
        }

        // 4. Текст "штуки и итоги"
        function getCurrentCalcNameShtText() {
            // Создаем элемент с вложенной структурой
            var $tempContainer = $('<div class="shtit_red" style="display: none;"><div class="table_info"></div></div>');
            $('body').append($tempContainer);

            var $tempElement = $tempContainer.find('.table_info');
            var computedColor = $tempElement.css('color');
            var result = computedColor && computedColor !== 'rgba(0, 0, 0, 0)' ? rgbToHex(computedColor) : '#000000';

            $tempContainer.remove();
            return result;
        }

        // 5. Фон "услуги и ндс"
        function getCurrentCalcNameShtYslBac() {
            var $tempElement = $('<div class="yslnds_red" style="display: none;"></div>');
            $('body').append($tempElement);

            var computedBg = $tempElement.css('background-color');
            var result = computedBg && computedBg !== 'rgba(0, 0, 0, 0)' && computedBg !== 'transparent' ? rgbToHex(computedBg) : '#808080';

            $tempElement.remove();
            return result;
        }

        // 6. Текст "услуги и ндс"
        function getCurrentCalcNameShtYslText() {
            var $tempContainer = $('<div class="yslnds_red" style="display: none;"><div class="table_info"></div></div>');
            $('body').append($tempContainer);

            var $tempElement = $tempContainer.find('.table_info');
            var computedColor = $tempElement.css('color');
            var result = computedColor && computedColor !== 'rgba(0, 0, 0, 0)' ? rgbToHex(computedColor) : '#000000';

            $tempContainer.remove();
            return result;
        }


        // ===== ПЕРВЫЙ ЦВЕТ (glav_color) =====
        var currentGlavColor = getCurrentGlavColor();
        console.log('Текущий цвет glav_color:', currentGlavColor);
        $('#kp_glav_color').val(currentGlavColor);


        // ===== ВТОРОЙ ЦВЕТ (two_color) =====
        var currentTwoColor = getCurrentTwoColor();
        console.log('Текущий цвет two_color:', currentTwoColor);
        $('#kp_two_color').val(currentTwoColor);


        // ===== ИНИЦИАЛИЗАЦИЯ ПРИ ЗАГРУЗКЕ - ТОЧНО ТАК ЖЕ =====
        console.log('=== НАЧАЛО ИНИЦИАЛИЗАЦИИ ЦВЕТОВ ТАБЛИЦЫ ===');

        // 1. Граница заголовков таблицы
        var currentCalcNameBord = getCurrentCalcNameBord();
        console.log('УСТАНАВЛИВАЕМ calc_name_bord:', currentCalcNameBord);
        $('#calc_name_bord').val(currentCalcNameBord);

        // 2. Цвет текста заголовков таблицы
        var currentKpCalcNameText = getCurrentKpCalcNameText();
        console.log('УСТАНАВЛИВАЕМ kp_calc_name_text:', currentKpCalcNameText);
        $('#kp_calc_name_text').val(currentKpCalcNameText);

        // 3. Фон "штуки и итоги"
        var currentCalcNameShtBac = getCurrentCalcNameShtBac();
        console.log('УСТАНАВЛИВАЕМ calc_name_sht_bac:', currentCalcNameShtBac);
        $('#calc_name_sht_bac').val(currentCalcNameShtBac);

        // 4. Текст "штуки и итоги"
        var currentCalcNameShtText = getCurrentCalcNameShtText();
        console.log('УСТАНАВЛИВАЕМ calc_name_sht_text:', currentCalcNameShtText);
        $('#calc_name_sht_text').val(currentCalcNameShtText);

        // 5. Фон "услуги и ндс"
        var currentCalcNameShtYslBac = getCurrentCalcNameShtYslBac();
        console.log('УСТАНАВЛИВАЕМ calc_name_sht_ysl_bac:', currentCalcNameShtYslBac);
        $('#calc_name_sht_ysl_bac').val(currentCalcNameShtYslBac);

        // 6. Текст "услуги и ндс"
        var currentCalcNameShtYslText = getCurrentCalcNameShtYslText();
        console.log('УСТАНАВЛИВАЕМ calc_name_sht_ysl_text:', currentCalcNameShtYslText);
        $('#calc_name_sht_ysl_text').val(currentCalcNameShtYslText);

        console.log('=== ЗАВЕРШЕНИЕ ИНИЦИАЛИЗАЦИИ ЦВЕТОВ ТАБЛИЦЫ ===');

        function checkZakImageExists() {
            const zakImageUrl = '/wp-content/uploads/crm_files/shablon/assets/img/zak.png';

            return fetch(zakImageUrl, { method: 'HEAD' })
                .then(response => response.ok)
                .catch(() => false);
        }

        // При загрузке страницы проверяем
        checkZakImageExists().then(hasZakImage => {
            console.log('Есть картинка zak.png?', hasZakImage);

            // 🔥 1. СНАЧАЛА проверяем CSS на цвет (вне зависимости от картинки)
            checkCssForZakladkaColor().then(cssColor => {
                console.log('Цвет в CSS файле:', cssColor || 'не найден');

                if (cssColor && cssColor !== '#ffffff' && cssColor !== '#ffffff') {
                    // 🔥 ЕСТЬ ЦВЕТ В CSS - показываем цвет (даже если есть картинка!)
                    console.log('🎨 Приоритет у ЦВЕТА из CSS:', cssColor);

                    removeDefaultZakladkaImage();

                    // 1. Обновляем input
                    $('#kp_zakladka_color').val(cssColor);

                    // 2. Удаляем картинку если она есть
                    $('.zakladka::before').css('background-image', 'none');

                    // 3. Применяем цвет
                    const style = document.createElement('style');
                    style.id = 'zakladka-bg-color';
                    style.innerHTML = `
                .zakladka::before {
                    background-color: ${cssColor} !important;
                    background-image: none !important;
                    border-radius: 50% !important;
                }
            `;
                    document.head.appendChild(style);

                    // 4. Обновляем кнопки редактора
                    $('#zakladkaBtn img, #zakladkaBtnOne img').hide();

                }
                else if (hasZakImage) {
                    // 🔥 НЕТ ЦВЕТА В CSS, НО ЕСТЬ КАРТИНКА
                    console.log('✅ Показываем картинку (цвета в CSS нет)');
                    $('style#zakladka-bg-color').remove();
                    $('#kp_zakladka_color').val('#ffffff');
                    $('#zakladkaBtn img, #zakladkaBtnOne img').show();
                    $('.zak-color-indicator').remove();

                }
                else {
                    // 🔥 НИЧЕГО НЕТ - дефолтное состояние
                    console.log('ℹ️ Нет ни цвета в CSS, ни картинки');
                    $('style#zakladka-bg-color').remove();
                    $('#kp_zakladka_color').val('#ffffff');
                }
            });
        });

        function removeDefaultZakladkaImage() {
            console.log('Убираем дефолтную картинку плагина ВЕЗДЕ...');

            // 1. Убираем через CSS стили
            $('style#remove-default-zakladka').remove();
            const style = document.createElement('style');
            style.id = 'remove-default-zakladka';
            style.innerHTML = `
        /* 🔥 Скрываем дефолтную картинку в кнопках */
        .zakladka-btn img[src*="zakladka.png"] {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }
        
        /* 🔥 Убираем дефолтную картинку из :before элементов */
        .zakladka::before {
            background-image: none !important;
        }
        
        /* 🔥 Убираем дефолтную картинку из .zakladka_red */
        .zakladka_red::before {
            background-image: none !important;
        }
        
        /* 🔥 Убираем CSS переменную с дефолтной картинкой */
        :root {
            --zakladka-image: none !important;
        }
    `;
            document.head.appendChild(style);

            // 2. Прямо скрываем картинки в кнопках (на всякий случай)
            $('#zakladkaBtn img, #zakladkaBtnOne img').hide();

            // 3. 🔥 ВАЖНО: Убираем дефолтную картинку через JavaScript у всех элементов
            $('.zakladka').each(function () {
                // Прямое удаление background-image у ::before через computed style
                $(this).css('--zakladka-image', 'none');
            });

            // 4. Убираем атрибуты src с дефолтной картинкой
            $('img[src*="zakladka.png"]').each(function () {
                var src = $(this).attr('src');
                if (src && src.includes('zakladka.png')) {
                    $(this).attr('src', ''); // Очищаем src
                }
            });
        }

        // 🔥 Функция для обновления кнопок с цветом
        function updateEditorButtonsWithColor(color) {
            $('#zakladkaBtn, #zakladkaBtnOne').each(function () {
                var $btn = $(this);

                // Удаляем старые индикаторы
                $btn.find('.zak-color-indicator').remove();

                // Добавляем цветной индикатор
                var $indicator = $('<div class="zak-color-indicator"></div>');
                $indicator.css({
                    'width': '20px',
                    'height': '20px',
                    'background-color': color,
                    'border-radius': '50%',
                    'position': 'absolute',
                    'top': '50%',
                    'left': '50%',
                    'transform': 'translate(-50%, -50%)',
                    'border': '2px solid white',
                    'z-index': '10'
                });

                $btn.append($indicator);
                $btn.css('background-color', color);
            });
        }

        // Функция проверки CSS
        function checkCssForZakladkaColor() {
            return fetch('/wp-content/uploads/crm_files/shablon/assets/css/style_kp.css')
                .then(r => r.ok ? r.text() : null)
                .then(css => {
                    if (!css) return null;
                    const match = css.match(/\.zakladka::before\s*\{[^}]*background-color:\s*([^;!]+)/);
                    return match ? match[1].trim() : null;
                })
                .catch(() => null);
        }



        // Функция применения цвета фона
        function applyZakladkaColor(color) {
            console.log('Применяем цвет фона закладки:', color);

            // 1. Убираем картинку из псевдоэлемента
            $('.zakladka::before').css('background-image', 'none');
            document.documentElement.style.setProperty('--zakladka-image', 'none');
            document.documentElement.style.setProperty('--zakladka-red', 'none');

            // 2. Убираем картинки из кнопок редактора
            $('#zakladkaBtn img, #zakladkaBtnOne img').attr('src', '').css({
                'visibility': 'hidden',
                'opacity': '0'
            });

            // 3. Меняем сами кнопки на цветные
            $('#zakladkaBtn, #zakladkaBtnOne').css({
                'background-color': color,
                'border-radius': '0 0 15px 15px',
                'position': 'relative'
            });

            // 4. Удаляем старые стили
            $('style#zakladka-bg-color').remove();

            // 5. Создаем новый стиль для закладок
            const style = document.createElement('style');
            style.id = 'zakladka-bg-color';
            style.innerHTML = `
        .zakladka::before {
            background-color: ${color} !important;
            background-image: none !important;
            border-radius: 50% !important;
        }
        
        .zakladka_red::before {
            background-image: none !important;
        }
    `;
            document.head.appendChild(style);
        }
    });


    jQuery(document).ready(function ($) {

        $('.kp_glav_color').hover(
            function () {
                // при наведении (mouseenter)
                $('.glav_color').addClass('hov');
            },
            function () {
                // при уходе (mouseleave)
                $('.glav_color').removeClass('hov');
            }
        );
        // ===== ПЕРВЫЙ ЦВЕТ (glav_color) =====
        $('.red_color_glav').click(function (e) {
            e.preventDefault();
            $('#kp_glav_color').click();
        });

        $('#kp_glav_color').on('change input', function () {
            var selectedColor = $(this).val();
            console.log('Выбран glav_color:', selectedColor);
            $('.glav_color').css('cssText', 'color: ' + selectedColor + "!important");
            // $('.glav_color h1, .glav_color h2, .glav_color h3').css('cssText', 'color: ' + selectedColor + "!important");
        });

        $('.kp_two_color ').hover(
            function () {
                // при наведении (mouseenter)
                $('.two_color').addClass('hov');
            },
            function () {
                // при уходе (mouseleave)
                $('.two_color').removeClass('hov');
            }
        );


        // ===== ВТОРОЙ ЦВЕТ (two_color) =====
        $('.red_color_two').click(function (e) {
            e.preventDefault();
            $('#kp_two_color').click();
        });

        $('#kp_two_color').on('change input', function () {
            var selectedColor = $(this).val();
            console.log('Выбран two_color:', selectedColor);
            $('.two_color').css('cssText', 'color: ' + selectedColor + "!important");
        });



        $('.tabl_calc_style').hover(
            function () {
                // при наведении (mouseenter)
                $('.textcols_more ').addClass('tablehov');
                $('.instcalk').addClass('instcalchov');
            },
            function () {
                // при уходе (mouseleave)
                $('.textcols_more ').removeClass('tablehov');
                $('.instcalk').removeClass('instcalchov');
            }
        );

        $('.bord_hov').hover(
            function () {
                // при наведении (mouseenter)
                $('.table_cont  ').addClass('hov');
            },
            function () {
                // при уходе (mouseleave)
                $('.table_cont  ').removeClass('hov');
            }
        );



        $('.bord_hov').hover(
            function () {
                // при наведении (mouseenter)
                $('.table_cont  ').addClass('hov');
            },
            function () {
                // при уходе (mouseleave)
                $('.table_cont  ').removeClass('hov');
            }
        );



        // ---таблица -калькулятор
        // граница заголовоков
        $('.calc_name_bord').click(function (e) {
            e.preventDefault();
            $('#calc_name_bord').click();
        });

        $('#calc_name_bord').on('change input', function () {
            var selectedColor = $(this).val();
            console.log('Выбран table_cont_red:граница', selectedColor);

            // Применяем с !important
            $('.table_cont_red').each(function () {
                this.style.setProperty('border', '1px solid ' + selectedColor, 'important');
            });
        });

        // цвет текста заголовоков
        $('.kp_calc_name_text').click(function (e) {
            e.preventDefault();
            $('#kp_calc_name_text').click();
        });

        $('#kp_calc_name_text').on('change input', function () {
            var selectedColor = $(this).val();
            console.log('Выбран table_cont_red:текст', selectedColor);

            // Применяем с !important
            $('.table_cont_red').each(function () {
                this.style.setProperty('color', selectedColor, 'important');
            });
        });


        $('.sht_hov').hover(
            function () {
                // при наведении (mouseenter)
                $('.shtit_red').addClass('strokehov');
            },
            function () {
                // при уходе (mouseleave)
                $('.shtit_red').removeClass('strokehov');
            }
        );

        $('.sht_hov').hover(
            function () {
                // при наведении (mouseenter)
                $('.shtit_red').addClass('strokehov');
            },
            function () {
                // при уходе (mouseleave)
                $('.shtit_red').removeClass('strokehov');
            }
        );



        // штуки и итоги задний фон
        $('.calc_name_sht_bac').click(function (e) {
            e.preventDefault();
            $('#calc_name_sht_bac').click();
        });

        $('#calc_name_sht_bac').on('change input', function () {
            var selectedColor = $(this).val();
            console.log('Выбран shtit_red:фон', selectedColor);

            // Применяем с !important
            $('.shtit_red').each(function () {
                this.style.setProperty('background', selectedColor, 'important');
            });
        });

        // штуки и итоги текст
        $('.calc_name_sht_text').click(function (e) {
            e.preventDefault();
            $('#calc_name_sht_text').click();
        });

        $('#calc_name_sht_text').on('change input', function () {
            var selectedColor = $(this).val();
            console.log('Выбран shtit_red:текст', selectedColor);

            // Уже использует !important
            $('.shtit_red .table_info').each(function () {
                this.style.setProperty('color', selectedColor, 'important');
            });
        });

        $('.ysl_hov').hover(
            function () {
                // при наведении (mouseenter)
                $('.yslnds_red').addClass('strokehov');
            },
            function () {
                // при уходе (mouseleave)
                $('.yslnds_red').removeClass('strokehov');
            }
        );


        $('.ysl_hov').hover(
            function () {
                // при наведении (mouseenter)
                $('.yslnds_red').addClass('strokehov');
            },
            function () {
                // при уходе (mouseleave)
                $('.yslnds_red').removeClass('strokehov');
            }
        );


        // услуги и ндс задний фон
        $('.calc_name_sht_ysl_bac').click(function (e) {
            e.preventDefault();
            $('#calc_name_sht_ysl_bac').click();
        });

        $('#calc_name_sht_ysl_bac').on('change input', function () {
            var selectedColor = $(this).val();
            console.log('Выбран yslnds_red:фон', selectedColor);

            // Применяем с !important
            $('.yslnds_red').each(function () {
                this.style.setProperty('background', selectedColor, 'important');
            });
        });

        // услуги и ндс текст
        $('.calc_name_sht_ysl_text').click(function (e) {
            e.preventDefault();
            $('#calc_name_sht_ysl_text').click();
        });

        $('#calc_name_sht_ysl_text').on('change input', function () {
            var selectedColor = $(this).val();
            console.log('Выбран yslnds_red:текст', selectedColor);

            // Уже использует !important
            $('.yslnds_red .table_info').each(function () {
                this.style.setProperty('color', selectedColor, 'important');
            });
        });
        // document-header p
     

        $('.zak_wrap').hover(
            function () {
                // при наведении (mouseenter)
                $('.zakladka_red').addClass('hovbef');
                $('.inst1').addClass('insthov1');
                $('.inst2').addClass('insthov2');
            },
            function () {
                // при уходе (mouseleave)
                $('.zakladka_red').removeClass('hovbef');
                $('.inst1').removeClass('insthov1');
                $('.inst2').removeClass('insthov2');
            }
        );



        // ===== ЦВЕТ ФОНА ЗАКЛАДКИ =====
        $('.red_color_zakladka').click(function (e) {
            e.preventDefault();
            $('#kp_zakladka_color').click();

        });

        $('#kp_zakladka_color').on('change input', function () {
            var selectedColor = $(this).val();
            console.log('Выбран цвет фона закладки:', selectedColor);
            $('#kp_zakladka_color').addClass('zakladka_fon');

            // Удаляем все стили с картинкой
            $('style#zakladka-permanent-style, style#zakladka-preview-style').remove();

            // Создаем стиль ТОЛЬКО с цветом
            const style = document.createElement('style');
            style.id = 'zakladka-bg-color';
            style.innerHTML = `
        .zakladka::before {
            background-color: ${selectedColor} !important;
            background-image: none !important;
            border-radius: 50% !important;
        }
        `;
            document.head.appendChild(style);

            // Обновляем кнопки редактора - показываем ЦВЕТ вместо иконки
            $('#zakladkaBtn, #zakladkaBtnOne').each(function () {
                var $btn = $(this);
                $btn.find('img').remove();
                if (!$btn.find('.zak-color-dot').length) {
                    $btn.html('<div class="zak-color-dot" style="width: 20px; height: 20px; background-color: ' + selectedColor + '; border-radius: 50%; display: block;"></div>');
                } else {
                    $btn.find('.zak-color-dot').css('background-color', selectedColor);
                }
            });

            // Сохраняем в localStorage что выбран цвет
            localStorage.setItem('zakladka_type', 'color');
            localStorage.removeItem('zakladka_url');
        });



        // ===== ИКОНКА ЗАКЛАДКИ =====
        $('.zakladka_pic').click(function (e) {
            e.preventDefault();
            $('#kp_zakladka_color').removeClass('zakladka_fon');
            var $button = $(this);
            var frame = wp.media({
                title: 'Выберите иконку для закладки',
                button: { text: 'Выбрать иконку' },
                library: { type: 'image' },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                $button.text('Сохраняем...').prop('disabled', true);

                // Показываем предпросмотр
                showIconPreview(attachment.url);

                // Обновляем кнопки редактора - показываем ИКОНКУ вместо цвета
                $('#zakladkaBtn, #zakladkaBtnOne').each(function () {
                    var $btn = $(this);
                    // Удаляем цветную точку если есть
                    $btn.find('.zak-color-dot').remove();

                    // Устанавливаем белый фон для кнопки при показе иконки
                    $btn.css({
                        'background-color': 'white',
                        'border': '1px solid #ddd'
                    });

                    // Добавляем или обновляем иконку
                    if (!$btn.find('img').length) {
                        $btn.html('<img draggable="false" role="img" class="emoji" alt="" src="' + attachment.url + '" style="background: white; padding: 2px; border-radius: 3px;">');
                    } else {
                        $btn.find('img').attr('src', attachment.url).css({
                            'background': 'white',
                            'padding': '2px',
                            'border-radius': '3px'
                        });
                    }
                });

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'crm_save_icon',
                        image_id: attachment.id
                    },
                    success: function (response) {
                        console.log('✅ Ответ сервера:', response);
                        if (response.success) {
                            updateCssIconVariable(response.data.url);
                            $button.text('✓ Сохранено').css({
                                'background': '#4CAF50',
                                'color': 'white'
                            });

                            // Удаляем цветовой стиль если он был
                            $('style#zakladka-bg-color').remove();

                            // Сохраняем в localStorage что выбрана иконка
                            localStorage.setItem('zakladka_type', 'icon');
                            localStorage.setItem('zakladka_url', response.data.url);

                            // НЕ возвращаем текст кнопки - оставляем "✓ Сохранено"
                            $button.text('Иконка').prop('disabled', false).css({
                                'background': '',
                                'color': ''
                            });
                        }
                        else {
                            console.error('❌ Ошибка от сервера:', response.data);
                            alert('Ошибка: ' + response.data);
                            $button.text('Ошибка').css('background', 'red');
                            $button.prop('disabled', false);
                        }
                    },
                    error: function () {
                        console.error('❌ AJAX ошибка:', status, error);
                        console.error('Ответ сервера:', xhr.responseText);
                        $button.text('Ошибка сети').prop('disabled', false);
                    }
                });
            });

            frame.open();
        });

        // Функция для предпросмотра иконки
        function showIconPreview(url) {
            $('.zakladka-icon-preview').remove();
            $('style#zakladka-preview-style').remove();
            const previewStyle = document.createElement('style');
            previewStyle.id = 'zakladka-preview-style';
            previewStyle.innerHTML = `
        .zakladka::before {
            background-image: url('${url}')!important ;
            background-size: contain !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
            z-index: 100 !important;
        }
        `;
            document.head.appendChild(previewStyle);
        }


        function updateCssIconVariable(url) {
            console.log('updateCssIconVariable вызвана с URL:', url);

            // Удаляем временный стиль превью
            $('style#zakladka-preview-style').remove();

            // 🔥 1. СРАЗУ обновляем как твой код
            document.documentElement.style.setProperty('--zakladka-image', 'url(' + url + ')');
            $('img[src*="zakladka.png"]').attr('src', url);
            $('#zakladkaBtn img, #zakladkaBtnOne img').attr('src', url);

            // Создаем постоянный стиль для новой иконки
            $('style#zakladka-permanent-style').remove();
            const permanentStyle = document.createElement('style');
            permanentStyle.id = 'zakladka-permanent-style';
            permanentStyle.innerHTML = `
    .zakladka::before {
        background-image: url('${url}') !important ;
        background-size: contain !important;
        background-repeat: no-repeat !important;
        background-position: center !important;
        background-color: transparent !important;
        
    }
    `;
            document.head.appendChild(permanentStyle);



            // 🔥 Функция проверки zak.png
            function checkZakPermanentAndUpdate() {
                const zakPermanentUrl = '/wp-content/uploads/crm_files/shablon/assets/img/zak.png';

                // 🔥 ВСЕГДА проверяем zak.png при загрузке
                fetch(zakPermanentUrl, { method: 'HEAD' })
                    .then(response => {
                        if (response.ok) {
                            console.log('✅ zak.png найден! Используем его...');
                            const permanentUrl = zakPermanentUrl + '?v=' + Date.now();

                            // Обновляем ВСЕ изображения
                            $('img[src*="zak"]').attr('src', permanentUrl);
                            $('#zakladkaBtn img, #zakladkaBtnOne img').attr('src', permanentUrl);

                            // Обновляем стиль
                            $('style#zakladka-permanent-style').remove();
                            const zakStyle = document.createElement('style');
                            zakStyle.id = 'zakladka-permanent-style';
                            zakStyle.innerHTML = `
                    .zakladka::before {
                        background-image: url('${permanentUrl}');
                        background-size: contain !important;
                        background-repeat: no-repeat !important;
                        background-position: center !important;
                    }
                `;
                            document.head.appendChild(zakStyle);
                        }
                    })
                    .catch(error => {
                        console.log('⚠️ zak.png не найден');
                    });
            }
        }


        // ИНИЦИАЛИЗАЦИЯ ПРИ ЗАГРУЗКЕ
        var initialGlavColor = $('#kp_glav_color').val();
        var initialTwoColor = $('#kp_two_color').val();
        var initialZakladkaColor = $('#kp_zakladka_color').val();

        // Проверяем localStorage что было выбрано ранее
        var savedType = localStorage.getItem('zakladka_type');

        if (initialGlavColor) {
            $('.glav_color').css('cssText', 'color: ' + initialGlavColor + "!important");
        }

        if (initialTwoColor) {
            $('.two_color').css('cssText', 'color: ' + initialTwoColor + "!important");
        }


    });


    jQuery(document).ready(function ($) {
        $('.shab_kp').click(function (e) {
            e.preventDefault();

            var $button = $(this);
            var ajaxUrl = $button.data('ajax-url');

            // ===== 1. СОБИРАЕМ ВСЕ ПУТИ И ЦВЕТА =====
            var backgroundImage = $('.file-content-editor').css('background-image');
            var bgUrl = backgroundImage.replace(/url\(['"]?(.*?)['"]?\)/, '$1');
            var logoUrl = $('img.logo_kp').attr('src') || $('img[class*="logo"]').attr('src');
            var avatarUrl = $('img.avatar').attr('src');
            var glavColor = $('#kp_glav_color').val();
            var twoColor = $('#kp_two_color').val();
            var zakladkaColor = $('#kp_zakladka_color').val();
            // стили для таблицы-калькулятор перменные
            // ДОБАВЛЯЕМ 6 НОВЫХ ЦВЕТОВ ДЛЯ ТАБЛИЦЫ-КАЛЬКУЛЯТОРА
            var calcnamebord = $('#calc_name_bord').val(); // граница заголовков таблицы
            var calcnametext = $('#kp_calc_name_text').val(); // текст заголовков таблицы
            var calcnmeshtbac = $('#calc_name_sht_bac').val(); // фон "штуки и итоги"
            var calcnmeshttext = $('#calc_name_sht_text').val(); // текст "штуки и итоги"
            var calcnmeshtyslbac = $('#calc_name_sht_ysl_bac').val(); // фон "услуги и ндс"
            var calcnmeshtysltext = $('#calc_name_sht_ysl_text').val(); // текст "услуги и ндс"

            var zakladkaUrl = $('#zakladkaBtn img').attr('src') ||
                $('#zakladkaBtnOne img').attr('src') ||
                $('.zakladka-btn img').attr('src') ||
                $('img[src*="zakladka"]').attr('src');

            console.log('Путь к закладке:', zakladkaUrl);
            console.log('Цвет закладки:', zakladkaColor);


            var bgPath = processImagePath(bgUrl, 'kp_prev');
            var logoPath = processImagePath(logoUrl, 'logokp_prev');
            var avatarPath = processImagePath(avatarUrl, 'avatarkp_prev');
            // 🔥 ДОБАВЬТЕ ПУТЬ К ЗАКЛАДКЕ
            var zakladkaPath = processImagePath(zakladkaUrl, 'zak_prev');

            // ===== 2. ОБРАБАТЫВАЕМ ПУТИ =====
            function processImagePath(imageUrl, expectedPrefix) {
                if (!imageUrl) return null;
                if (imageUrl.startsWith('/') && !imageUrl.startsWith('//')) {
                    imageUrl = window.location.origin + imageUrl;
                }
                if (!imageUrl.startsWith('http')) {
                    var uploadsPath = '/wp-content/uploads/crm_files/shablon/assets/img/';
                    if (imageUrl.startsWith(expectedPrefix + '.')) {
                        imageUrl = window.location.origin + uploadsPath + imageUrl;
                    }
                }
                imageUrl = imageUrl.split('?')[0];
                var relativePath = imageUrl.replace(window.location.origin, '').replace(/^\//, '');
                if (relativePath.startsWith(expectedPrefix + '.')) {
                    relativePath = 'wp-content/uploads/crm_files/shablon/assets/img/' + relativePath;
                }
                return relativePath;
            }

            var bgPath = processImagePath(bgUrl, 'kp_prev');
            var logoPath = processImagePath(logoUrl, 'logokp_prev');
            var avatarPath = processImagePath(avatarUrl, 'avatarkp_prev');

            var zakladkaPath = null;
            if (zakladkaUrl && zakladkaUrl.includes('zak_prev')) {
                zakladkaPath = processImagePath(zakladkaUrl, 'zak_prev');
            }


            // ===== 3. ОТПРАВЛЯЕМ ДАННЫЕ =====
            $button.text('Сохраняем все...').prop('disabled', true);
            var zakladkaPath = null;
            var isZakladkaImage = false;

            // Определяем, что выбрано
            if ($('#zakladkaBtn img').length > 0 || $('#zakladkaBtnOne img').length > 0) {
                isZakladkaImage = true;

                if (zakladkaUrl) {
                    // Всегда отправляем путь к файлу
                    zakladkaPath = processImagePath(zakladkaUrl, 'zak_prev');
                    console.log('Отправляем путь закладки на сервер:', zakladkaPath);
                }
            }
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'update_kp',
                    background_path: bgPath,
                    logo_path: logoPath,
                    avatar_path: avatarPath,
                    zakladka_path: zakladkaPath,
                    zakladka_is_image: isZakladkaImage ? 'yes' : 'no', // 'yes' если картинка
                    zakladka_color: $('#kp_zakladka_color').hasClass('zakladka_fon') ? zakladkaColor : '',
                    // ДОБАВЛЯЕМ 6 НОВЫХ ПАРАМЕТРОВ ДЛЯ ТАБЛИЦЫ
                    calc_name_bord: calcnamebord,
                    kp_calc_name_text: calcnametext,
                    calc_name_sht_bac: calcnmeshtbac,
                    calc_name_sht_text: calcnmeshttext,
                    calc_name_sht_ysl_bac: calcnmeshtyslbac,
                    calc_name_sht_ysl_text: calcnmeshtysltext,
                    // 
                    glav_color: glavColor,
                    two_color: twoColor,
                    clear_cache: 'all', // 
                    nocache: Date.now() // <-- И ЭТОТ для предотвращения кеширования запроса
                },
                success: function (response) {
                    console.log('Ответ сервера:', response);

                    if (response.success) {
                        console.log('=== НАЧАЛО ОБНОВЛЕНИЯ ===');

                        if ('caches' in window) {
                            caches.keys().then(function (names) {
                                for (let name of names) {
                                    caches.delete(name);
                                }
                                console.log('🗑️ Кеш браузера очищен');
                            });
                        }

                        // ===== ПРОСТО МЕНЯЕМ _prev В URL ИЗОБРАЖЕНИЙ =====

                        // 1. Логотип
                        var currentLogoSrc = $('.logo_kp').attr('src');
                        if (currentLogoSrc && currentLogoSrc.includes('_prev')) {
                            var newLogoSrc = currentLogoSrc.split('?')[0].replace('_prev.', '.') + '?v=' + Date.now();
                            $('.logo_kp').attr('src', newLogoSrc);
                            console.log('Логотип обновлен:', currentLogoSrc, '→', newLogoSrc);
                        }

                        // 2. Аватарка
                        var currentAvatarSrc = $('.avatar').attr('src');
                        if (currentAvatarSrc && currentAvatarSrc.includes('_prev')) {
                            var newAvatarSrc = currentAvatarSrc.split('?')[0].replace('_prev.', '.') + '?v=' + Date.now();
                            $('.avatar').attr('src', newAvatarSrc);
                            console.log('Аватарка обновлена:', currentAvatarSrc, '→', newAvatarSrc);
                        }

                        // 3. Фон (если нужно)
                        var currentBg = $('.file-content-editor').css('background-image');
                        if (currentBg && currentBg.includes('_prev')) {
                            var newBg = currentBg.replace('_prev.', '.');
                            $('.file-content-editor').css('background-image', newBg);
                        }

                        // 4 закладка

                        var currentZakladkaSrc = $('#zakladkaBtn img').attr('src') ||
                            $('#zakladkaBtnOne img').attr('src');

                        if (currentZakladkaSrc && currentZakladkaSrc.includes('_prev')) {
                            // Если загрузили новую картинку
                            var newZakladkaSrc = currentZakladkaSrc.split('?')[0].replace('_prev.', '.') + '?v=' + Date.now();

                            $('#zakladkaBtn img').attr('src', newZakladkaSrc);
                            $('#zakladkaBtnOne img').attr('src', newZakladkaSrc);
                            $('.zakladka-btn img').attr('src', newZakladkaSrc);

                            console.log('Закладка-картинка обновлена:', currentZakladkaSrc, '→', newZakladkaSrc);

                            // Обновляем CSS переменную
                            document.documentElement.style.setProperty('--zakladka-image', 'url(' + newZakladkaSrc + ')');

                        } else if (zakladkaColor && zakladkaColor !== 'transparent') {


                            // 🔥 ПРОВЕРЯЕМ ЕСТЬ ЛИ КЛАСС zakladka_fon
                            var hasZakladkaFonClass = $('#kp_zakladka_color').hasClass('zakladka_fon');

                            if (hasZakladkaFonClass) {
                                console.log('💡 Применяем и сохраняем цвет закладки:', zakladkaColor);

                                // 🔥 Если выбрали ЦВЕТ (не прозрачный)
                                console.log('💡 Применяем цвет закладки:', zakladkaColor);

                                var hasZakladkaFonClass = $('#kp_zakladka_color').hasClass('zakladka_fon');


                                // Убираем картинки из кнопок
                                $('#zakladkaBtn img, #zakladkaBtnOne img').hide();

                                // Добавляем цветные индикаторы
                                $('#zakladkaBtn, #zakladkaBtnOne').each(function () {
                                    if (!$(this).find('.zak-color-indicator').length) {
                                        $(this).append('<div class="zak-color-indicator"></div>');
                                    }
                                    $(this).find('.zak-color-indicator').css({
                                        'width': '20px',
                                        'height': '20px',
                                        'background-color': zakladkaColor,
                                        'border-radius': '50%',
                                        'position': 'absolute',
                                        'top': '50%',
                                        'left': '50%',
                                        'transform': 'translate(-50%, -50%)',
                                        'border': '2px solid white'
                                    });
                                });

                                // Обновляем закладки в документе
                                $('style#zakladka-final-color').remove();
                                const style = document.createElement('style');
                                style.id = 'zakladka-final-color';
                                style.innerHTML = `
                            .zakladka::before {
                                background-color: ${zakladkaColor} !important;
                                background-image: none !important;
                                border-radius: 50% !important;
                            }
                            
                            .zakladka_red::before {
                                background-image: none !important;
                            }
                        `;
                                document.head.appendChild(style);

                                $('#kp_zakladka_color').removeClass('zakladka_fon');
                                console.log('✅ Класс zakladka_fon удален');
                            } else {
                                console.log('💡 Цвет закладки уже сохранен ранее, только применяем визуально');
                            }
                        }

                        // ===== 5. ПРИМЕНЯЕМ ЦВЕТА ТОЛЬКО К НУЖНЫМ ЭЛЕМЕНТАМ =====

                        // 5.1 Применяем glav_color
                        if (glavColor) {
                            $('<style id="temp-glav-color">')
                                .html('.glav_color { color: ' + glavColor + ' !important; }')
                                .appendTo('head');
                            console.log('Glav_color применен:', glavColor);
                        }

                        // 5.2 Применяем two_color
                        if (twoColor) {
                            $('<style id="temp-two-color">')
                                .html('.two_color { color: ' + twoColor + ' !important; }')
                                .appendTo('head');
                            console.log('Two_color применен:', twoColor);
                        }

                        // ДОБАВЛЯЕМ: ПРИМЕНЕНИЕ 6 НОВЫХ ЦВЕТОВ ДЛЯ ТАБЛИЦЫ
                        // 5.3 Применяем calc_name_bord (граница заголовков таблицы)
                        if (calcnamebord) {
                            $('<style id="temp-calc-name-bord">')
                                .html('.table_cont_red { border: 1px solid ' + calcnamebord + ' !important; }')
                                .appendTo('head');
                            console.log('calc_name_bord применен:', calcnamebord);
                        }

                        // 5.4 Применяем kp_calc_name_text (текст заголовков таблицы)
                        if (calcnametext) {
                            $('<style id="temp-kp-calc-name-text">')
                                .html('.table_cont_red { color: ' + calcnametext + ' !important; }')
                                .appendTo('head');
                            console.log('kp_calc_name_text применен:', calcnametext);
                        }

                        // 5.5 Применяем calc_name_sht_bac (фон "штуки и итоги")
                        if (calcnmeshtbac) {
                            $('<style id="temp-calc-name-sht-bac">')
                                .html('.shtit_red { background: ' + calcnmeshtbac + ' !important; }')
                                .appendTo('head');
                            console.log('calc_name_sht_bac применен:', calcnmeshtbac);
                        }

                        // 5.6 Применяем calc_name_sht_text (текст "штуки и итоги")
                        if (calcnmeshttext) {
                            $('<style id="temp-calc-name-sht-text">')
                                .html('.shtit_red .table_info { color: ' + calcnmeshttext + ' !important; }')
                                .appendTo('head');
                            console.log('calc_name_sht_text применен:', calcnmeshttext);
                        }

                        // 5.7 Применяем calc_name_sht_ysl_bac (фон "услуги и ндс")
                        if (calcnmeshtyslbac) {
                            $('<style id="temp-calc-name-sht-ysl-bac">')
                                .html('.yslnds_red { background: ' + calcnmeshtyslbac + ' !important; }')
                                .appendTo('head');
                            console.log('calc_name_sht_ysl_bac применен:', calcnmeshtyslbac);
                        }

                        // 5.8 Применяем calc_name_sht_ysl_text (текст "услуги и ндс")
                        if (calcnmeshtysltext) {
                            $('<style id="temp-calc-name-sht-ysl-text">')
                                .html('.yslnds_red .table_info { color: ' + calcnmeshtysltext + ' !important; }')
                                .appendTo('head');
                            console.log('calc_name_sht_ysl_text применен:', calcnmeshtysltext);
                        }
                        // КОНЕЦ ДОБАВЛЕНИЯ ПРИМЕНЕНИЯ НОВЫХ ЦВЕТОВ

                        // ===== ОСТАЛЬНОЙ КОД =====
                        $button.text('✓ Все сохранено').css({
                            'background': '#4CAF50',
                            'color': 'white'
                        });

                        // ===== АВТОМАТИЧЕСКОЕ ОБНОВЛЕНИЕ PDF =====
                        setTimeout(function () {
                            // ... существующий код обновления PDF ...
                        }, 500);

                        // ===== ВОЗВРАЩАЕМ КНОПКУ =====
                        setTimeout(function () {
                            $button.text('Сохранить стили').prop('disabled', false).css({
                                'background': '',
                                'color': ''
                            });

                            // Удаляем временные стили через 2 секунды
                            setTimeout(function () {
                                $('#temp-glav-color, #temp-two-color, ' +
                                    '#temp-calc-name-bord, #temp-kp-calc-name-text, ' +
                                    '#temp-calc-name-sht-bac, #temp-calc-name-sht-text, ' +
                                    '#temp-calc-name-sht-ysl-bac, #temp-calc-name-sht-ysl-text').remove();
                            }, 2000);
                        }, 2000);

                    } else {
                        alert('Ошибка: ' + (response.data || 'Неизвестная ошибка'));
                        $button.text('Сохранить стили').prop('disabled', false);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX ошибка:', error);
                    alert('Ошибка сети или сервера');
                    $button.text('Сохранить стили').prop('disabled', false);
                }
            });
        });
    });


});




console.log('=== ЛОКАЛЬНЫЕ ФУНКЦИИ В ФАЙЛЕ ===');

// Массив функций из вашего кода
const localFunctions = [];

// Ищем все function declarations в текущем scope
try {
    // Этот метод покажет функции в текущей области видимости
    console.log('Функции в текущем scope:');

    // Перебираем все свойства текущего scope
    for (let prop in this) {
        if (typeof this[prop] === 'function' &&
            !prop.startsWith('$') &&
            !prop.startsWith('jQuery')) {
            console.log(`- ${prop}`);
            localFunctions.push(prop);
        }
    }
} catch (e) {
    console.log('Нельзя прочитать локальные функции напрямую');
}