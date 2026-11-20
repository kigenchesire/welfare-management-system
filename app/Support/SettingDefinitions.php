<?php

namespace App\Support;

/**
 * Single source of truth for every configurable setting: its group, form type,
 * label and the default used when seeding a fresh installation.
 *
 * Defaults are for the first deployment (Luxurious Welfare Group). Another group
 * changes them from System Settings / Website Management - no code changes needed.
 */
class SettingDefinitions
{
    public const GROUPS = [
        'general' => 'General',
        'website' => 'Website',
        'financial' => 'Financial',
        'sms' => 'SMS Gateway',
    ];

    public static function all(): array
    {
        return [
            // ---------------- General ----------------
            'group_name' => ['general', 'text', 'Welfare group name', 'Luxurious Welfare Group'],
            'short_name' => ['general', 'text', 'Short name', 'LWG'],
            'description' => ['general', 'textarea', 'Short description', 'A members-driven welfare group that stands with its members and their families in times of bereavement.'],
            'registration_info' => ['general', 'text', 'Group registration information', 'Registered self-help welfare group'],
            'address' => ['general', 'text', 'Physical address', 'Nairobi, Kenya'],
            'email' => ['general', 'text', 'Email address', 'info@luxuriouswelfare.org'],
            'phone' => ['general', 'text', 'Phone number', '+254 700 000 000'],
            'website_url' => ['general', 'text', 'Website URL', ''],
            'primary_color' => ['general', 'color', 'Primary colour', '#1f6f5c'],
            'secondary_color' => ['general', 'color', 'Secondary colour', '#c89b3c'],
            'logo' => ['general', 'image', 'Official logo', ''],
            'favicon' => ['general', 'image', 'Favicon', ''],

            // ---------------- Website ----------------
            'hero_heading' => ['website', 'text', 'Hero heading', 'Standing together in times of need'],
            'hero_description' => ['website', 'textarea', 'Hero description', 'We support our members and their families when they lose a loved one, through fair, transparent and timely contributions.'],
            'hero_image' => ['website', 'image', 'Hero / group photograph', ''],
            'welcome_message' => ['website', 'textarea', 'Welcome message', 'Welcome to our welfare family. Together we carry each other\'s burdens.'],
            'about_us' => ['website', 'textarea', 'About us', 'We are a welfare group formed by friends and colleagues who believe that no member should face bereavement alone. Members contribute an agreed amount whenever a registered member or their beneficiary passes on.'],
            'objectives' => ['website', 'textarea', 'Purpose and objectives (one per line)', "Support members financially during bereavement\nPromote unity and togetherness among members\nKeep accurate and transparent welfare records"],
            'mission' => ['website', 'textarea', 'Mission', 'To provide timely and dignified support to members and their families during bereavement.'],
            'vision' => ['website', 'textarea', 'Vision', 'A united community where no member grieves alone.'],
            'values' => ['website', 'textarea', 'Values (one per line)', "Unity\nTransparency\nAccountability\nCompassion"],
            'how_membership' => ['website', 'textarea', 'How it works: Membership', 'Membership is open to anyone who agrees to the group\'s rules and contributes when a case is raised.'],
            'how_registration' => ['website', 'textarea', 'How it works: Registration', 'Fill in the registration form with your details and your beneficiaries. The Secretary reviews and approves your application.'],
            'how_cases' => ['website', 'textarea', 'How it works: Welfare cases', 'When a member or a registered beneficiary passes on, the Secretary opens a welfare case.'],
            'how_contributions' => ['website', 'textarea', 'How it works: Contributions', 'Every active member is invoiced the agreed contribution for the case category.'],
            'how_prepayments' => ['website', 'textarea', 'How it works: Prepayments', 'You may deposit money in advance. Contributions are deducted automatically from your wallet.'],
            'how_tracking' => ['website', 'textarea', 'How it works: Payment tracking', 'Log in at any time to see your wallet balance, invoices and full payment history.'],
            'rep_name' => ['website', 'text', 'Representative name', 'Jane Doe'],
            'rep_designation' => ['website', 'text', 'Representative designation', 'Chairperson'],
            'rep_bio' => ['website', 'textarea', 'Representative short description', 'Leads the group with a commitment to transparency and service to all members.'],
            'rep_photo' => ['website', 'image', 'Representative photograph', ''],
            'contact_hours' => ['website', 'text', 'Contact hours', 'Monday - Friday, 8:00am - 5:00pm'],
            'facebook_url' => ['website', 'text', 'Facebook URL', ''],
            'twitter_url' => ['website', 'text', 'X / Twitter URL', ''],
            'instagram_url' => ['website', 'text', 'Instagram URL', ''],
            'whatsapp_number' => ['website', 'text', 'WhatsApp number', ''],
            'footer_text' => ['website', 'textarea', 'Footer text', 'Together we stand, in good times and in bad.'],

            // ---------------- Financial ----------------
            'currency' => ['financial', 'text', 'Currency', 'KES'],
            'contribution_parent' => ['financial', 'number', 'Contribution: parent case', '600'],
            'contribution_parent_in_law' => ['financial', 'number', 'Contribution: parent-in-law case', '600'],
            'contribution_self' => ['financial', 'number', 'Contribution: self (member) case', '1100'],
            'contribution_spouse' => ['financial', 'number', 'Contribution: spouse case', '1100'],
            'contribution_child' => ['financial', 'number', 'Contribution: child case', '1100'],
            'office_share_per_case' => ['financial', 'number', 'Office expenditure taken first from each member payment on a case', '100'],
            'transport_share_per_case' => ['financial', 'number', 'Transport taken next from each member payment on a case', '50'],
            'payment_method' => ['financial', 'text', 'Payment method name', 'Cosmopolitan Sacco Paybill'],
            'paybill_number' => ['financial', 'text', 'Paybill number', '000000'],
            'account_instructions' => ['financial', 'text', 'Account number instructions', 'Use your National ID number as the account number'],
            'payment_instructions' => ['financial', 'textarea', 'Payment instructions', "Go to M-Pesa > Lipa na M-Pesa > Paybill\nEnter the business number\nEnter your National ID as the account number\nEnter the amount and confirm"],
            'member_no_prefix' => ['financial', 'text', 'Member number prefix', 'LWG'],
            'low_balance_threshold' => ['financial', 'number', 'Low wallet balance alert below', '1100'],
            'auto_apply_deposits' => ['financial', 'boolean', 'Use new deposits to settle outstanding invoices automatically', '1'],

            // ---------------- SMS ----------------
            'sms_enabled' => ['sms', 'boolean', 'Enable SMS sending', '0'],
            'sms_provider' => ['sms', 'select', 'SMS provider', 'africastalking'],
            'sms_api_url' => ['sms', 'text', 'API URL', 'https://api.africastalking.com/version1/messaging'],
            'sms_username' => ['sms', 'text', 'API username', ''],
            'sms_api_key' => ['sms', 'password', 'API key / token', ''],
            'sms_sender_id' => ['sms', 'text', 'Sender ID', ''],
            'sms_tpl_case' => ['sms', 'textarea', 'Template: new welfare case', 'Dear {name}, {group} regrets to announce the passing of {deceased} ({category}) of member {member}. Case {case_no}. Your contribution: {currency} {amount}. Outstanding: {currency} {outstanding}. Paybill {paybill}.'],
            'sms_tpl_invoice' => ['sms', 'textarea', 'Template: invoice', 'Dear {name}, invoice {invoice_no} of {currency} {amount} for {title} has been raised. Outstanding: {currency} {outstanding}. Paybill {paybill}. {group}'],
            'sms_tpl_payment' => ['sms', 'textarea', 'Template: payment confirmation', 'Dear {name}, we have received {currency} {amount} (Ref {reference}). Wallet balance: {currency} {balance}. Thank you. {group}'],
            'sms_tpl_case_reminder' => ['sms', 'textarea', 'Template: case contribution reminder', 'Dear {name}, reminder: your contribution for case {case_no} ({deceased}) is outstanding. Please pay {currency} {outstanding} via Paybill {paybill}. {group}'],
            'sms_tpl_low_balance' => ['sms', 'textarea', 'Template: low balance reminder', 'Dear {name}, your {group} wallet balance is {currency} {balance}. Kindly top up via Paybill {paybill}.'],
            'sms_tpl_credentials' => ['sms', 'textarea', 'Template: account approved', 'Dear {name}, your {group} membership is approved. Member No {member_no}. Login: {login_url} Username: {username} Password: {password}'],
        ];
    }

    public static function forGroup(string $group): array
    {
        return array_filter(self::all(), fn ($d) => $d[0] === $group);
    }

    public const SMS_PROVIDERS = [
        'africastalking' => "Africa's Talking",
        'generic' => 'Generic HTTP (JSON POST)',
    ];
}
