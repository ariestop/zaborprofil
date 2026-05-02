<?php

declare(strict_types=1);

namespace App\Module\Auth\Domain\Security;

final class AdminPermission
{
    public const string PAGES_VIEW = 'pages.view';
    public const string PAGES_CREATE = 'pages.create';
    public const string PAGES_EDIT = 'pages.edit';
    public const string PAGES_PUBLISH = 'pages.publish';
    public const string PAGES_DELETE = 'pages.delete';
    public const string SEO_EDIT = 'seo.edit';
    public const string MEDIA_UPLOAD = 'media.upload';
    public const string MEDIA_DELETE = 'media.delete';
    public const string LEADS_VIEW = 'leads.view';
    public const string LEADS_MANAGE = 'leads.manage';
    public const string SETTINGS_EDIT = 'settings.edit';
    public const string USERS_MANAGE = 'users.manage';
    public const string SYSTEM_VIEW = 'system.view';
    public const string SYSTEM_MANAGE = 'system.manage';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PAGES_VIEW,
            self::PAGES_CREATE,
            self::PAGES_EDIT,
            self::PAGES_PUBLISH,
            self::PAGES_DELETE,
            self::SEO_EDIT,
            self::MEDIA_UPLOAD,
            self::MEDIA_DELETE,
            self::LEADS_VIEW,
            self::LEADS_MANAGE,
            self::SETTINGS_EDIT,
            self::USERS_MANAGE,
            self::SYSTEM_VIEW,
            self::SYSTEM_MANAGE,
        ];
    }
}
