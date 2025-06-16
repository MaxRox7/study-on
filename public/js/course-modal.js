/* JavaScript для модального окна курсов и защиты баланса */
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking Bootstrap...');
    
    // Проверяем, загружен ли Bootstrap
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded!');
        
        // Fallback: простая реализация без Bootstrap
        document.querySelectorAll('[data-bs-toggle="modal"]').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Fallback modal trigger');
                
                const targetSelector = this.getAttribute('data-bs-target');
                const modal = document.querySelector(targetSelector);
                
                if (modal) {
                    // Заполняем данные модального окна
                    const courseTitle = this.getAttribute('data-course-title');
                    const courseType = this.getAttribute('data-course-type');
                    const coursePrice = this.getAttribute('data-course-price');
                    const formId = this.getAttribute('data-form-id');
                    
                    modal.querySelector('#operationType').textContent = courseType.toLowerCase();
                    modal.querySelector('#courseTitle').textContent = courseTitle;
                    modal.querySelector('#coursePrice').textContent = coursePrice;
                    
                    // Показываем модальное окно
                    modal.style.display = 'block';
                    modal.classList.add('show');
                    modal.setAttribute('aria-hidden', 'false');
                    
                    // Добавляем backdrop
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    backdrop.id = 'modal-backdrop';
                    document.body.appendChild(backdrop);
                    document.body.classList.add('modal-open');
                    
                    // Настраиваем кнопку подтверждения
                    const confirmButton = modal.querySelector('#confirmButton');
                    confirmButton.onclick = function() {
                        console.log('Confirm clicked, submitting form:', formId);
                        document.getElementById(formId).submit();
                    };
                    
                    // Обработчики закрытия
                    const closeButtons = modal.querySelectorAll('[data-bs-dismiss="modal"]');
                    closeButtons.forEach(function(closeBtn) {
                        closeBtn.onclick = function() {
                            modal.style.display = 'none';
                            modal.classList.remove('show');
                            modal.setAttribute('aria-hidden', 'true');
                            const backdrop = document.getElementById('modal-backdrop');
                            if (backdrop) backdrop.remove();
                            document.body.classList.remove('modal-open');
                        };
                    });
                    
                    // Закрытие по клику на backdrop
                    backdrop.onclick = function() {
                        closeButtons[0].click();
                    };
                }
            });
        });
        return;
    }
    
    console.log('Bootstrap loaded successfully');
    
    // Стандартная Bootstrap реализация
    const confirmModal = document.getElementById('confirmModal');
    if (confirmModal) {
        console.log('Modal element found');
        
        confirmModal.addEventListener('show.bs.modal', function(event) {
            console.log('Modal show event triggered');
            const button = event.relatedTarget;
            
            if (!button) {
                console.error('No button found in event.relatedTarget');
                return;
            }
            
            const courseTitle = button.getAttribute('data-course-title');
            const courseType = button.getAttribute('data-course-type');
            const coursePrice = button.getAttribute('data-course-price');
            const formId = button.getAttribute('data-form-id');
            
            console.log('Modal data:', {courseTitle, courseType, coursePrice, formId});
            
            if (!courseTitle || !courseType || !coursePrice || !formId) {
                console.error('Missing data attributes on button');
                return;
            }
            
            document.getElementById('operationType').textContent = courseType.toLowerCase();
            document.getElementById('courseTitle').textContent = courseTitle;
            document.getElementById('coursePrice').textContent = coursePrice;
            
            const confirmButton = document.getElementById('confirmButton');
            confirmButton.onclick = function() {
                console.log('Confirm button clicked, submitting form:', formId);
                const form = document.getElementById(formId);
                if (form) {
                    form.submit();
                } else {
                    console.error('Form not found:', formId);
                }
            };
        });
        
        confirmModal.addEventListener('shown.bs.modal', function() {
            console.log('Modal shown successfully');
        });
        
        confirmModal.addEventListener('hidden.bs.modal', function() {
            console.log('Modal hidden');
        });
    } else {
        console.error('Modal element not found');
    }
    
    // Проверяем кнопки
    const buyButtons = document.querySelectorAll('[data-bs-toggle="modal"]');
    console.log('Found buy buttons:', buyButtons.length);
    
    buyButtons.forEach(function(btn, index) {
        console.log('Button ' + index + ':', {
            target: btn.getAttribute('data-bs-target'),
            title: btn.getAttribute('data-course-title'),
            type: btn.getAttribute('data-course-type'),
            price: btn.getAttribute('data-course-price'),
            formId: btn.getAttribute('data-form-id')
        });
    });
});

// Простая защита баланса от удаления
(function() {
    const balanceAlert = document.getElementById('balanceAlert');
    if (balanceAlert) {
        console.log('✅ Баланс защищен от автоудаления');
        
        // Защита от удаления через MutationObserver
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.removedNodes.forEach(function(node) {
                        if (node === balanceAlert || (node.contains && node.contains(balanceAlert))) {
                            console.warn('⚠️ Попытка удаления баланса заблокирована');
                            // Возвращаем баланс обратно
                            mutation.target.insertBefore(balanceAlert, mutation.nextSibling);
                        }
                    });
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
})(); 