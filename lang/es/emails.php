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

    // Historial de los últimos 30 días.
    'exchange_rate_history_title' => 'Historial de los últimos :days días',
    'exchange_rate_history_summary' => ':records registros · máximo :max · mínimo :min',
    'exchange_rate_history_col_date' => 'Fecha',
    'exchange_rate_history_col_rate' => 'Tipo de cambio',
    'exchange_rate_history_col_change' => 'Variación',
    'exchange_rate_history_col_factor' => 'Factor',
    'exchange_rate_history_notice' => 'La variación es contra el registro anterior de la tabla. Los días sin publicación aplicable no aparecen.',
    'exchange_rate_history_empty' => 'Todavía no hay registros en el periodo.',
    'exchange_rate_row_today' => 'Hoy',
    'exchange_rate_row_tomorrow' => 'Mañana',
    'exchange_rate_row_applicable' => 'Fecha del aviso',
    'exchange_rate_row_empty' => 'Sin registro',
    'exchange_rate_source_manual' => 'Captura manual',
    'exchange_rate_source_carried' => 'Feriado · TC del :date',

    // Catálogo de factores por rango.
    'exchange_rate_factors_title' => 'Factores vigentes por rango',
    'exchange_rate_factors_col_code' => 'Clave',
    'exchange_rate_factors_col_range' => 'Rango de la publicación',
    'exchange_rate_factors_col_factor' => 'Equivale a',
    'exchange_rate_factors_range' => ':from a :to',
    'exchange_rate_factors_notice' => 'El límite inferior del rango es inclusivo y el superior exclusivo. El factor es informativo y no modifica el tipo de cambio.',
    'exchange_rate_factors_empty' => 'No hay factores vigentes capturados.',

    'holiday_reminder_subject' => 'Captura pendiente de días feriados :year',
    'holiday_reminder_title' => 'Días feriados :year',
    'holiday_reminder_heading' => 'Aún no se capturan los días feriados de :year.',
    'holiday_reminder_body' => 'El calendario de días feriados determina el día hábil siguiente con el que se calcula el tipo de cambio. Sin los días de :year, el cálculo de fin de año puede tomar una fecha equivocada.',
    'holiday_reminder_notice' => 'Este recordatorio se enviará todos los días hasta que se capture el primer día feriado del año.',
    'holiday_reminder_button' => 'Capturar días feriados',

    'footer_support' => 'Este correo fue enviado desde una dirección no monitoreada. Si tiene dudas con respecto a su contenido, póngase en contacto al siguiente correo: ',
    'footer_rights' => 'Todos los derechos reservados.',
    'override_notice' => 'Este correo se generó en modo de pruebas. En producción se habría enviado a :recipient.',
];
