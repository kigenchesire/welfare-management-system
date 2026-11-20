<?php

namespace App\Support;

/** Permission catalogue and the default role assignments seeded on install. */
class Permissions
{
    public const ALL = [
        'Membership' => [
            'members.view' => 'View members',
            'members.manage' => 'Register and edit members',
            'members.approve' => 'Approve / reject registrations',
            'beneficiaries.manage' => 'Add, edit and remove beneficiaries',
        ],
        'Welfare' => [
            'cases.view' => 'View welfare cases',
            'cases.manage' => 'Create, close and cancel welfare cases',
            'invoices.view' => 'View invoices',
            'invoices.manage' => 'Create and cancel invoices',
        ],
        'Finance' => [
            'wallets.view' => 'View member wallets and transactions',
            'deposits.record' => 'Record deposits and reverse payments',
            'adjustments.manage' => 'Post wallet adjustments',
            'finance.view' => 'View financial overview',
            'office.view' => 'View the office expenditure account',
            'office.manage' => 'Top up the office account and record office expenses',
            'disbursements.manage' => 'Record and void welfare case disbursements',
        ],
        'Reports' => [
            'reports.members' => 'Member reports',
            'reports.welfare' => 'Welfare reports',
            'reports.financial' => 'Financial reports',
        ],
        'Communication' => [
            'announcements.manage' => 'Send announcements',
            'sms.view' => 'View SMS logs',
        ],
        'Administration' => [
            'users.manage' => 'Manage user accounts',
            'roles.manage' => 'Manage roles and permissions',
            'audit.view' => 'View audit logs',
            'settings.manage' => 'Manage system settings',
            'website.manage' => 'Manage website content and images',
        ],
    ];

    public static function names(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::ALL)));
    }

    public static function defaults(): array
    {
        return [
            'Administrator' => self::names(),
            'Chairperson' => [
                'members.view', 'cases.view', 'invoices.view', 'wallets.view', 'finance.view', 'office.view',
                'reports.members', 'reports.welfare', 'reports.financial', 'sms.view', 'audit.view',
            ],
            'Secretary' => [
                'members.view', 'members.manage', 'members.approve', 'beneficiaries.manage',
                'cases.view', 'cases.manage', 'invoices.view', 'reports.members', 'reports.welfare',
                'announcements.manage', 'sms.view', 'website.manage',
            ],
            'Treasurer' => [
                'members.view', 'cases.view', 'invoices.view', 'invoices.manage', 'wallets.view',
                'deposits.record', 'adjustments.manage', 'finance.view',
                'office.view', 'office.manage', 'disbursements.manage', 'reports.financial', 'reports.welfare', 'sms.view',
            ],
            'Member' => [],
        ];
    }
}
