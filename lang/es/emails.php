<?php

declare(strict_types=1);

return [
    'greeting' => 'Hola :name,',

    'welcome_subject' => 'Bienvenido a la plataforma',
    'welcome_title' => 'Bienvenido',
    'welcome_intro' => 'Su cuenta fue creada correctamente. Estas son sus credenciales de acceso:',
    'email_label' => 'Correo electrónico',
    'password_label' => 'Contraseña temporal',
    'must_change_password' => 'Por seguridad, deberá cambiar esta contraseña la primera vez que inicie sesión.',
    'login_button' => 'Iniciar sesión',
    'login_url_label' => 'O acceda directamente desde:',

    'test_subject' => 'Correo de prueba',
    'test_body' => 'Este es un correo de prueba enviado desde la configuración del sistema. Si lo recibió, el envío de correo funciona correctamente.',
    'test_sent_at' => 'Enviado el :datetime.',

    'holiday_reminder_subject' => 'Captura pendiente de días feriados :year',
    'holiday_reminder_title' => 'Días feriados :year',
    'holiday_reminder_heading' => 'Aún no se capturan los días feriados de :year.',
    'holiday_reminder_body' => 'El calendario de días feriados determina el día hábil siguiente con el que se calcula el tipo de cambio. Sin los días de :year, el cálculo de fin de año puede tomar una fecha equivocada.',
    'holiday_reminder_notice' => 'Este recordatorio se enviará todos los días hasta que se capture el primer día feriado del año.',
    'holiday_reminder_button' => 'Capturar días feriados',

    'footer_support' => '¿Dudas? Escriba a',
    'footer_rights' => 'Todos los derechos reservados.',
    'override_notice' => 'Este correo se generó en modo de pruebas. En producción se habría enviado a :recipient.',
];
