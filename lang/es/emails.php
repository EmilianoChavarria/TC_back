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

    // Aviso del tipo de cambio a la lista de notificaciones.
    'exchange_rate_updated_subject' => 'Tipo de cambio :date: :rate',
    'exchange_rate_corrected_subject' => 'Corrección del tipo de cambio :date: :rate',
    'exchange_rate_updated_title' => 'Tipo de cambio del día',
    'exchange_rate_updated_heading' => 'Tipo de cambio vigente para:',
    'exchange_rate_label' => 'Tipo de cambio',
    'exchange_rate_factor_label' => 'Factor del rango:',
    'exchange_rate_factor_code' => 'clave :code',
    'exchange_rate_factor_notice' => 'Informativo: no modifica el tipo de cambio.',
    'exchange_rate_manual_notice' => 'Este valor se capturó manualmente.',
    'exchange_rate_correction_notice' => 'Este correo corrige el tipo de cambio enviado antes para esta fecha. Use el valor de abajo.',
    'exchange_rate_updated_button' => 'Ver en el portal',

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
