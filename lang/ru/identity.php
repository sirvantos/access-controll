<?php

declare(strict_types=1);

return [
    'email_already_registered' => 'Этот адрес электронной почты уже зарегистрирован.',
    'invitation_invalid' => 'Приглашение больше недействительно.',
    'invitation_not_pending' => 'Приглашение уже не ожидает принятия.',
    'last_active_admin' => 'В компании должен оставаться хотя бы один активный администратор.',
    'super_admin_created' => 'Создан суперадминистратор :email.',
    'password' => 'Пароль',
    'password_confirmation' => 'Подтвердите пароль',
    'roles' => [
        'company_admin' => 'Администратор компании',
        'viewer' => 'Наблюдатель',
    ],
    'invitation' => [
        'subject' => 'Вас пригласили',
        'intro' => 'Вас пригласили присоединиться в роли :role.',
        'action' => 'Принять приглашение',
        'expires' => 'Срок действия приглашения истекает :expires_at.',
    ],
    'password_reset' => [
        'subject' => 'Сброс пароля',
        'intro' => 'Перейдите по ссылке ниже, чтобы задать новый пароль.',
        'action' => 'Сбросить пароль',
    ],
];
