/* JavaScript для модального окна курсов и защиты баланса */
document.addEventListener('DOMContentLoaded', function() {
    // Проверяем, загружен ли Bootstrap
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded!');
        
        // Fallback: простая реализация без Bootstrap
        document.querySelectorAll('[data-bs-toggle="modal"]').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
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
    
    // Стандартная Bootstrap реализация
    const confirmModal = document.getElementById('confirmModal');
    if (confirmModal) {
        
        confirmModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            if (!button) {
                return;
            }
            
            const courseTitle = button.getAttribute('data-course-title');
            const courseType = button.getAttribute('data-course-type');
            const coursePrice = button.getAttribute('data-course-price');
            const formId = button.getAttribute('data-form-id');
            
            if (!courseTitle || !courseType || !coursePrice || !formId) {
                return;
            }
            
            document.getElementById('operationType').textContent = courseType.toLowerCase();
            document.getElementById('courseTitle').textContent = courseTitle;
            document.getElementById('coursePrice').textContent = coursePrice;
            
            const confirmButton = document.getElementById('confirmButton');
            confirmButton.onclick = function() {
                const form = document.getElementById(formId);
                if (form) {
                    form.submit();
                }
            };
        });
    }
});

// Простая защита баланса от удаления
(function() {
    const balanceAlert = document.getElementById('balanceAlert');
    if (balanceAlert) {
        // Защита от удаления через MutationObserver
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.removedNodes.forEach(function(node) {
                        if (node === balanceAlert || (node.contains && node.contains(balanceAlert))) {
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