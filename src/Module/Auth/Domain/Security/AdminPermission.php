<?php

declare(strict_types=1);

namespace App\Module\Auth\Domain\Security;

final class AdminPermission
{
    public const string PAGES_VIEW = 'pages.view';
    public const string PAGES_CREATE = 'pages.create';
    public const string PAGES_EDIT = 'pages.edit';
    public const string PAGES_PUBLISH = 'pages.publish';
    public const string PAGES_SUBMIT_REVIEW = 'pages.submit_review';
    public const string PAGES_APPROVE = 'pages.approve';
    public const string PAGES_UNPUBLISH = 'pages.unpublish';
    public const string PAGES_SCHEDULE = 'pages.schedule';
    public const string PAGES_ARCHIVE = 'pages.archive';
    public const string PAGES_VIEW_REVISIONS = 'pages.view_revisions';
    public const string PAGES_ROLLBACK_REVISION = 'pages.rollback_revision';
    public const string PAGES_MANAGE_TEMPLATES = 'pages.manage_templates';
    public const string PAGES_DELETE = 'pages.delete';
    public const string BLOCKS_CREATE = 'blocks.create';
    public const string BLOCKS_EDIT = 'blocks.edit';
    public const string BLOCKS_DELETE = 'blocks.delete';
    public const string BLOCKS_REORDER = 'blocks.reorder';
    public const string BLOCKS_CLONE = 'blocks.clone';
    public const string SEO_EDIT = 'seo.edit';
    public const string SEO_APPROVE = 'seo.approve';
    public const string MEDIA_UPLOAD = 'media.upload';
    public const string MEDIA_DELETE = 'media.delete';
    public const string LEADS_VIEW = 'leads.view';
    public const string LEADS_MANAGE = 'leads.manage';
    public const string CATALOG_VIEW = 'catalog.view';
    public const string CATALOG_MANAGE = 'catalog.manage';
    public const string SETTINGS_EDIT = 'settings.edit';
    public const string USERS_MANAGE = 'users.manage';
    public const string SYSTEM_VIEW = 'system.view';
    public const string SYSTEM_MANAGE = 'system.manage';
    public const string SYSTEM_DANGEROUS = 'system.dangerous';

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
            self::PAGES_SUBMIT_REVIEW,
            self::PAGES_APPROVE,
            self::PAGES_UNPUBLISH,
            self::PAGES_SCHEDULE,
            self::PAGES_ARCHIVE,
            self::PAGES_VIEW_REVISIONS,
            self::PAGES_ROLLBACK_REVISION,
            self::PAGES_MANAGE_TEMPLATES,
            self::PAGES_DELETE,
            self::BLOCKS_CREATE,
            self::BLOCKS_EDIT,
            self::BLOCKS_DELETE,
            self::BLOCKS_REORDER,
            self::BLOCKS_CLONE,
            self::SEO_EDIT,
            self::SEO_APPROVE,
            self::MEDIA_UPLOAD,
            self::MEDIA_DELETE,
            self::LEADS_VIEW,
            self::LEADS_MANAGE,
            self::CATALOG_VIEW,
            self::CATALOG_MANAGE,
            self::SETTINGS_EDIT,
            self::USERS_MANAGE,
            self::SYSTEM_VIEW,
            self::SYSTEM_MANAGE,
            self::SYSTEM_DANGEROUS,
        ];
    }
}
