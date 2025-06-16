document.addEventListener('DOMContentLoaded', function() {
    const registrationForm = document.getElementById('registrationForm');
    
    if (registrationForm) {
        registrationForm.addEventListener('submit', function(event) {
            var password = document.getElementById('password').value;
            var confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                event.preventDefault();  // Останавливаем отправку формы
                document.getElementById('passwordError').style.display = 'block'; // Показываем сообщение об ошибке
            } else {
                document.getElementById('passwordError').style.display = 'none'; // Скрываем сообщение об ошибке
            }
        });
    }
}); 