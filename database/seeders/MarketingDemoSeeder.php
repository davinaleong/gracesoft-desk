<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Service;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MarketingDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedAdminUser();

        TimeEntry::query()
            ->where('notes', 'like', 'Demo Seed:%')
            ->forceDelete();

        Transaction::query()
            ->where('transaction_code', 'like', 'DEMO-TRX-%')
            ->forceDelete();

        Service::query()
            ->whereHas('vendor', fn ($q) => $q->whereIn('name', [
                'Singtel', 'Amazon Web Services', 'Adobe', 'Cloudflare',
                'GitHub', 'Figma', 'Postmark', 'Notion',
            ]))
            ->forceDelete();

        Vendor::query()
            ->whereIn('name', [
                'Singtel', 'Amazon Web Services', 'Adobe', 'Cloudflare',
                'GitHub', 'Figma', 'Postmark', 'Notion',
            ])
            ->forceDelete();

        $stagesByName = $this->seedProjectStages();
        $categoriesBySlug = $this->seedTransactionCategories();
        $methodsBySlug = $this->seedPaymentMethods();
        $accountsByCode = $this->seedAccounts();
        $projectsByCode = $this->seedProjects();

        $this->seedTimeEntries($projectsByCode, $stagesByName);
        $this->seedTransactions($projectsByCode, $categoriesBySlug, $methodsBySlug, $accountsByCode);
        $this->syncAccountBalances();
        $this->seedVendorsAndServices();
    }

    private function seedAdminUser(): void
    {
        $attributes = [
            'email' => env('ADMIN_EMAIL', 'admin@gracesoft.demo'),
            'name' => env('ADMIN_NAME', 'GraceSoft Demo Admin'),
            'password' => Hash::make(env('ADMIN_TEMP_PASSWORD', 'ChangeMe123!')),
            'must_change_password' => true,
            'password_changed_at' => null,
        ];

        $existingUser = User::query()->first();

        if ($existingUser) {
            $existingUser->update($attributes);

            return;
        }

        User::query()->create($attributes);
    }

    /**
     * @return array<string, ProjectStage>
     */
    private function seedProjectStages(): array
    {
        $stages = [
            ['name' => 'Discovery', 'sort_order' => 1],
            ['name' => 'Analysis', 'sort_order' => 2],
            ['name' => 'Design', 'sort_order' => 3],
            ['name' => 'Development', 'sort_order' => 4],
            ['name' => 'Testing', 'sort_order' => 5],
            ['name' => 'Deployment', 'sort_order' => 6],
            ['name' => 'Maintenance', 'sort_order' => 7],
        ];

        $byName = [];

        foreach ($stages as $stage) {
            $model = ProjectStage::query()->updateOrCreate(
                ['name' => $stage['name']],
                [
                    'sort_order' => $stage['sort_order'],
                    'status' => 'active',
                ]
            );

            $byName[$stage['name']] = $model;
        }

        return $byName;
    }

    /**
     * @return array<string, TransactionCategory>
     */
    private function seedTransactionCategories(): array
    {
        $categories = [
            ['name' => 'Enterprise Retainer', 'slug' => 'enterprise-retainer', 'type' => 'income'],
            ['name' => 'Implementation Milestone', 'slug' => 'implementation-milestone', 'type' => 'income'],
            ['name' => 'Training Revenue', 'slug' => 'training-revenue', 'type' => 'income'],
            ['name' => 'SaaS & Infrastructure', 'slug' => 'saas-infrastructure', 'type' => 'expense'],
            ['name' => 'Paid Media', 'slug' => 'paid-media', 'type' => 'expense'],
            ['name' => 'Contractor Fees', 'slug' => 'contractor-fees', 'type' => 'expense'],
            ['name' => 'Operations', 'slug' => 'operations', 'type' => 'expense'],
        ];

        $bySlug = [];

        foreach ($categories as $category) {
            $model = TransactionCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'type' => $category['type'],
                    'is_active' => true,
                ]
            );

            $bySlug[$category['slug']] = $model;
        }

        return $bySlug;
    }

    /**
     * @return array<string, PaymentMethod>
     */
    private function seedPaymentMethods(): array
    {
        $methods = [
            ['name' => 'Bank Transfer', 'slug' => 'bank-transfer'],
            ['name' => 'Corporate Card', 'slug' => 'corporate-card'],
            ['name' => 'PayNow', 'slug' => 'paynow'],
        ];

        $bySlug = [];

        foreach ($methods as $method) {
            $model = PaymentMethod::query()->updateOrCreate(
                ['slug' => $method['slug']],
                [
                    'name' => $method['name'],
                    'is_active' => true,
                ]
            );

            $bySlug[$method['slug']] = $model;
        }

        return $bySlug;
    }

    /**
     * @return array<string, Account>
     */
    private function seedAccounts(): array
    {
        $accounts = [
            ['name' => 'Operating Account', 'code' => 'BANK-OPERATING', 'type' => 'bank', 'opening_balance' => 80000],
            ['name' => 'Tax & Reserve Account', 'code' => 'BANK-RESERVE', 'type' => 'bank', 'opening_balance' => 30000],
            ['name' => 'Marketing Corporate Card', 'code' => 'CARD-MARKETING', 'type' => 'card', 'opening_balance' => 0],
        ];

        $byCode = [];

        foreach ($accounts as $account) {
            $model = Account::query()->updateOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'currency' => 'SGD',
                    'opening_balance' => $account['opening_balance'],
                    'is_active' => true,
                ]
            );

            $byCode[$account['code']] = $model;
        }

        return $byCode;
    }

    /**
     * @return array<string, Project>
     */
    private function seedProjects(): array
    {
        $today = CarbonImmutable::today();

        $projects = [
            [
                'code' => 'DEMO-HQX',
                'name' => 'GraceSoft HQ Experience Revamp',
                'status' => 'active',
                'description' => 'Customer-facing HQ dashboard and finance cockpit redesign for executive reporting.',
                'starts_on' => $today->subDays(120)->toDateString(),
                'ends_on' => $today->addDays(30)->toDateString(),
                'is_billable' => true,
            ],
            [
                'code' => 'DEMO-LAUNCH',
                'name' => 'LaunchPad Campaign Analytics',
                'status' => 'active',
                'description' => 'Cross-channel campaign tracking and attribution rollout for sales and marketing leaders.',
                'starts_on' => $today->subDays(95)->toDateString(),
                'ends_on' => $today->addDays(20)->toDateString(),
                'is_billable' => true,
            ],
            [
                'code' => 'DEMO-CRM',
                'name' => 'CRM Lifecycle Automation',
                'status' => 'active',
                'description' => 'Lead-to-customer workflow automation integrating CRM, billing and service desk handoff.',
                'starts_on' => $today->subDays(80)->toDateString(),
                'ends_on' => $today->addDays(35)->toDateString(),
                'is_billable' => true,
            ],
            [
                'code' => 'DEMO-SUPPORT',
                'name' => 'Customer Success Playbook',
                'status' => 'on-hold',
                'description' => 'Internal process hardening for support handoffs, onboarding and quarterly health reviews.',
                'starts_on' => $today->subDays(60)->toDateString(),
                'ends_on' => $today->addDays(45)->toDateString(),
                'is_billable' => false,
            ],
            [
                'code' => 'DEMO-AI',
                'name' => 'AI Support Copilot Discovery',
                'status' => 'completed',
                'description' => 'Discovery sprint to define MVP flows, guardrails and ROI baseline for AI-assisted support.',
                'starts_on' => $today->subDays(140)->toDateString(),
                'ends_on' => $today->subDays(25)->toDateString(),
                'is_billable' => false,
            ],
        ];

        $byCode = [];

        foreach ($projects as $project) {
            $model = Project::query()->updateOrCreate(
                ['code' => $project['code']],
                $project
            );

            $byCode[$project['code']] = $model;
        }

        return $byCode;
    }

    /**
     * @param  array<string, Project>  $projectsByCode
     * @param  array<string, ProjectStage>  $stagesByName
     */
    private function seedTimeEntries(array $projectsByCode, array $stagesByName): void
    {
        $userId = User::query()->value('id');
        $today = CarbonImmutable::today();

        if (! $userId) {
            return;
        }

        $plans = [
            [
                'project_code' => 'DEMO-HQX',
                'stages' => ['Discovery', 'Analysis', 'Design', 'Development', 'Testing', 'Deployment'],
                'start_days_ago' => 100,
                'sessions_per_stage' => 3,
            ],
            [
                'project_code' => 'DEMO-LAUNCH',
                'stages' => ['Discovery', 'Analysis', 'Development', 'Testing'],
                'start_days_ago' => 85,
                'sessions_per_stage' => 3,
            ],
            [
                'project_code' => 'DEMO-CRM',
                'stages' => ['Analysis', 'Design', 'Development', 'Testing', 'Maintenance'],
                'start_days_ago' => 70,
                'sessions_per_stage' => 3,
            ],
            [
                'project_code' => 'DEMO-SUPPORT',
                'stages' => ['Analysis', 'Maintenance'],
                'start_days_ago' => 50,
                'sessions_per_stage' => 4,
            ],
            [
                'project_code' => 'DEMO-AI',
                'stages' => ['Discovery', 'Analysis', 'Design'],
                'start_days_ago' => 130,
                'sessions_per_stage' => 3,
            ],
        ];

        $activityByStage = [
            'Discovery' => 'Stakeholder interview and scope alignment',
            'Analysis' => 'Backlog grooming and requirement refinement',
            'Design' => 'Interface wireframing and UX copy review',
            'Development' => 'Feature implementation and integration updates',
            'Testing' => 'Regression checks and UAT issue triage',
            'Deployment' => 'Release preparation and launch verification',
            'Maintenance' => 'Post-launch tuning and KPI review',
        ];

        $durationPattern = [60, 75, 90, 105, 120, 150];

        foreach ($plans as $plan) {
            $project = $projectsByCode[$plan['project_code']] ?? null;

            if (! $project) {
                continue;
            }

            $entryIndex = 0;

            foreach ($plan['stages'] as $stageName) {
                $stage = $stagesByName[$stageName] ?? null;

                if (! $stage) {
                    continue;
                }

                for ($session = 0; $session < $plan['sessions_per_stage']; $session++) {
                    $durationMinutes = $durationPattern[$entryIndex % count($durationPattern)];
                    $entryDate = $today
                        ->subDays($plan['start_days_ago'] - ($entryIndex * 2) - $session)
                        ->toDateString();
                    $hourlyRate = $project->is_billable ? 165 + (($entryIndex % 4) * 15) : 0;

                    TimeEntry::query()->create([
                        'project_id' => $project->id,
                        'project_stage_id' => $stage->id,
                        'user_id' => $userId,
                        'entry_date' => $entryDate,
                        'duration_minutes' => $durationMinutes,
                        'is_billable' => (bool) $project->is_billable,
                        'hourly_rate' => $hourlyRate,
                        'notes' => sprintf(
                            'Demo Seed: %s | %s | %s',
                            $project->code,
                            $stageName,
                            $activityByStage[$stageName] ?? 'Project delivery activity'
                        ),
                    ]);

                    $entryIndex++;
                }
            }
        }
    }

    /**
     * @param  array<string, Project>  $projectsByCode
     * @param  array<string, TransactionCategory>  $categoriesBySlug
     * @param  array<string, PaymentMethod>  $methodsBySlug
     * @param  array<string, Account>  $accountsByCode
     */
    private function seedTransactions(
        array $projectsByCode,
        array $categoriesBySlug,
        array $methodsBySlug,
        array $accountsByCode
    ): void {
        $today = CarbonImmutable::today();

        $transactions = [
            [
                'code' => 'DEMO-TRX-001',
                'date' => $today->subDays(120)->toDateString(),
                'type' => 'income',
                'direction' => 'in',
                'status' => 'completed',
                'project_code' => 'DEMO-HQX',
                'category_slug' => 'enterprise-retainer',
                'account_code' => 'BANK-OPERATING',
                'method_slug' => 'bank-transfer',
                'reference' => 'INV-2401-GS-HQX',
                'description' => 'January monthly retainer payment',
                'amount' => 14500,
                'gst' => 1160,
            ],
            [
                'code' => 'DEMO-TRX-002',
                'date' => $today->subDays(92)->toDateString(),
                'type' => 'income',
                'direction' => 'in',
                'status' => 'completed',
                'project_code' => 'DEMO-LAUNCH',
                'category_slug' => 'implementation-milestone',
                'account_code' => 'BANK-OPERATING',
                'method_slug' => 'bank-transfer',
                'reference' => 'INV-2410-LAUNCH',
                'description' => 'Campaign analytics milestone 1 sign-off',
                'amount' => 18900,
                'gst' => 1512,
            ],
            [
                'code' => 'DEMO-TRX-003',
                'date' => $today->subDays(84)->toDateString(),
                'type' => 'expense',
                'direction' => 'out',
                'status' => 'completed',
                'project_code' => 'DEMO-LAUNCH',
                'category_slug' => 'paid-media',
                'account_code' => 'CARD-MARKETING',
                'method_slug' => 'corporate-card',
                'reference' => 'MKT-Q1-PAID-021',
                'description' => 'LinkedIn and Google paid media spend',
                'amount' => 6400,
                'gst' => 0,
            ],
            [
                'code' => 'DEMO-TRX-004',
                'date' => $today->subDays(76)->toDateString(),
                'type' => 'expense',
                'direction' => 'out',
                'status' => 'completed',
                'project_code' => null,
                'category_slug' => 'saas-infrastructure',
                'account_code' => 'CARD-MARKETING',
                'method_slug' => 'corporate-card',
                'reference' => 'SaaS-STACK-APR',
                'description' => 'Design, analytics and monitoring subscriptions',
                'amount' => 1320,
                'gst' => 105.6,
            ],
            [
                'code' => 'DEMO-TRX-005',
                'date' => $today->subDays(72)->toDateString(),
                'type' => 'income',
                'direction' => 'in',
                'status' => 'completed',
                'project_code' => 'DEMO-CRM',
                'category_slug' => 'enterprise-retainer',
                'account_code' => 'BANK-OPERATING',
                'method_slug' => 'bank-transfer',
                'reference' => 'INV-2412-CRM',
                'description' => 'CRM automation monthly retainer',
                'amount' => 12400,
                'gst' => 992,
            ],
            [
                'code' => 'DEMO-TRX-006',
                'date' => $today->subDays(64)->toDateString(),
                'type' => 'expense',
                'direction' => 'out',
                'status' => 'completed',
                'project_code' => 'DEMO-CRM',
                'category_slug' => 'contractor-fees',
                'account_code' => 'BANK-OPERATING',
                'method_slug' => 'paynow',
                'reference' => 'CNT-UX-804',
                'description' => 'Contract UX writing and journey mapping support',
                'amount' => 2800,
                'gst' => 224,
            ],
            [
                'code' => 'DEMO-TRX-007',
                'date' => $today->subDays(56)->toDateString(),
                'type' => 'income',
                'direction' => 'in',
                'status' => 'completed',
                'project_code' => 'DEMO-HQX',
                'category_slug' => 'implementation-milestone',
                'account_code' => 'BANK-OPERATING',
                'method_slug' => 'bank-transfer',
                'reference' => 'INV-2420-HQX',
                'description' => 'HQ revamp UAT milestone payment',
                'amount' => 22300,
                'gst' => 1784,
            ],
            [
                'code' => 'DEMO-TRX-008',
                'date' => $today->subDays(48)->toDateString(),
                'type' => 'expense',
                'direction' => 'out',
                'status' => 'completed',
                'project_code' => null,
                'category_slug' => 'operations',
                'account_code' => 'BANK-OPERATING',
                'method_slug' => 'bank-transfer',
                'reference' => 'OPS-INSURANCE-2026',
                'description' => 'Annual professional indemnity insurance',
                'amount' => 1850,
                'gst' => 148,
            ],
            [
                'code' => 'DEMO-TRX-009',
                'date' => $today->subDays(42)->toDateString(),
                'type' => 'income',
                'direction' => 'in',
                'status' => 'completed',
                'project_code' => 'DEMO-LAUNCH',
                'category_slug' => 'training-revenue',
                'account_code' => 'BANK-OPERATING',
                'method_slug' => 'bank-transfer',
                'reference' => 'TRN-2424-LAUNCH',
                'description' => 'Enablement workshop for regional marketing team',
                'amount' => 5200,
                'gst' => 416,
            ],
            [
                'code' => 'DEMO-TRX-010',
                'date' => $today->subDays(34)->toDateString(),
                'type' => 'expense',
                'direction' => 'out',
                'status' => 'completed',
                'project_code' => 'DEMO-HQX',
                'category_slug' => 'contractor-fees',
                'account_code' => 'BANK-OPERATING',
                'method_slug' => 'paynow',
                'reference' => 'CNT-FE-992',
                'description' => 'Front-end specialist sprint extension',
                'amount' => 3400,
                'gst' => 272,
            ],
            [
                'code' => 'DEMO-TRX-011',
                'date' => $today->subDays(28)->toDateString(),
                'type' => 'income',
                'direction' => 'in',
                'status' => 'pending',
                'project_code' => 'DEMO-CRM',
                'category_slug' => 'implementation-milestone',
                'account_code' => 'BANK-OPERATING',
                'method_slug' => 'bank-transfer',
                'reference' => 'INV-2429-CRM',
                'description' => 'Go-live milestone invoice awaiting remittance',
                'amount' => 17800,
                'gst' => 1424,
            ],
            [
                'code' => 'DEMO-TRX-012',
                'date' => $today->subDays(20)->toDateString(),
                'type' => 'expense',
                'direction' => 'out',
                'status' => 'pending',
                'project_code' => null,
                'category_slug' => 'paid-media',
                'account_code' => 'CARD-MARKETING',
                'method_slug' => 'corporate-card',
                'reference' => 'MKT-Q2-PAID-043',
                'description' => 'Prospecting campaign spend pending statement close',
                'amount' => 2800,
                'gst' => 0,
            ],
        ];

        foreach ($transactions as $transaction) {
            $category = $categoriesBySlug[$transaction['category_slug']] ?? null;
            $method = $methodsBySlug[$transaction['method_slug']] ?? null;
            $account = $accountsByCode[$transaction['account_code']] ?? null;
            $project = $transaction['project_code'] ? ($projectsByCode[$transaction['project_code']] ?? null) : null;

            if (! $category || ! $method || ! $account) {
                continue;
            }

            Transaction::query()->updateOrCreate(
                ['transaction_code' => $transaction['code']],
                [
                    'account_id' => $account->id,
                    'transaction_category_id' => $category->id,
                    'payment_method_id' => $method->id,
                    'project_id' => $project?->id,
                    'type' => $transaction['type'],
                    'direction' => $transaction['direction'],
                    'status' => $transaction['status'],
                    'transaction_date' => $transaction['date'],
                    'reference' => $transaction['reference'],
                    'description' => $transaction['description'],
                    'amount' => $transaction['amount'],
                    'gst_amount' => $transaction['gst'],
                ]
            );
        }
    }

    private function seedVendorsAndServices(): void
    {
        $vendors = [
            [
                'name' => 'Singtel',
                'category' => 'telco',
                'website' => 'https://www.singtel.com',
                'support_url' => 'https://www.singtel.com/personal/support',
                'account_number' => 'SGL-8821-CORP',
                'status' => 'active',
                'notes' => 'Primary telco for mobile and broadband.',
                'services' => [
                    ['name' => 'Mobile Plan', 'plan' => 'XO 30', 'category' => 'communication', 'status' => 'active'],
                    ['name' => 'Broadband', 'plan' => 'Home Fibre 2Gbps', 'category' => 'communication', 'status' => 'active'],
                ],
            ],
            [
                'name' => 'Amazon Web Services',
                'category' => 'cloud',
                'website' => 'https://aws.amazon.com',
                'support_url' => 'https://aws.amazon.com/support',
                'account_number' => 'AWS-ACC-441200',
                'status' => 'active',
                'notes' => 'Primary cloud infrastructure provider. Billing via consolidated invoice.',
                'services' => [
                    ['name' => 'S3 Storage', 'plan' => 'Pay-as-you-go', 'category' => 'storage', 'status' => 'active'],
                    ['name' => 'EC2 Compute', 'plan' => 'On-Demand', 'category' => 'dev_tools', 'status' => 'active'],
                    ['name' => 'SES Email', 'plan' => 'Pay-as-you-go', 'category' => 'communication', 'status' => 'active'],
                    ['name' => 'CloudFront CDN', 'plan' => 'Pay-as-you-go', 'category' => 'security', 'status' => 'active'],
                ],
            ],
            [
                'name' => 'Adobe',
                'category' => 'saas',
                'website' => 'https://www.adobe.com',
                'support_url' => 'https://helpx.adobe.com',
                'account_number' => null,
                'status' => 'active',
                'notes' => 'Creative tools subscription for design team.',
                'services' => [
                    ['name' => 'Creative Cloud', 'plan' => 'All Apps', 'category' => 'design', 'status' => 'active'],
                ],
            ],
            [
                'name' => 'Cloudflare',
                'category' => 'cloud',
                'website' => 'https://www.cloudflare.com',
                'support_url' => 'https://support.cloudflare.com',
                'account_number' => null,
                'status' => 'active',
                'notes' => 'DNS, CDN and edge security for all production domains.',
                'services' => [
                    ['name' => 'DNS & CDN', 'plan' => 'Free', 'category' => 'security', 'status' => 'active'],
                    ['name' => 'R2 Storage', 'plan' => 'Pay-as-you-go', 'category' => 'storage', 'status' => 'active'],
                    ['name' => 'Zero Trust Access', 'plan' => 'Teams Free', 'category' => 'security', 'status' => 'active'],
                ],
            ],
            [
                'name' => 'GitHub',
                'category' => 'saas',
                'website' => 'https://github.com',
                'support_url' => 'https://support.github.com',
                'account_number' => null,
                'status' => 'active',
                'notes' => 'Source control and CI/CD for all engineering projects.',
                'services' => [
                    ['name' => 'Copilot', 'plan' => 'Individual', 'category' => 'dev_tools', 'status' => 'active'],
                    ['name' => 'Actions', 'plan' => 'Free Tier', 'category' => 'dev_tools', 'status' => 'active'],
                    ['name' => 'Advanced Security', 'plan' => 'Add-on', 'category' => 'security', 'status' => 'paused'],
                ],
            ],
            [
                'name' => 'Figma',
                'category' => 'saas',
                'website' => 'https://www.figma.com',
                'support_url' => 'https://help.figma.com',
                'account_number' => null,
                'status' => 'active',
                'notes' => 'Primary design and prototyping tool.',
                'services' => [
                    ['name' => 'Figma Professional', 'plan' => 'Professional', 'category' => 'design', 'status' => 'active'],
                ],
            ],
            [
                'name' => 'Postmark',
                'category' => 'saas',
                'website' => 'https://postmarkapp.com',
                'support_url' => 'https://postmarkapp.com/support',
                'account_number' => null,
                'status' => 'active',
                'notes' => 'Transactional email delivery for all Laravel apps.',
                'services' => [
                    ['name' => 'Transactional Email', 'plan' => '10k/month', 'category' => 'communication', 'status' => 'active'],
                ],
            ],
            [
                'name' => 'Notion',
                'category' => 'saas',
                'website' => 'https://www.notion.so',
                'support_url' => 'https://www.notion.so/help',
                'account_number' => null,
                'status' => 'inactive',
                'notes' => 'Previously used for internal knowledge base. Migrated to Confluence.',
                'services' => [
                    ['name' => 'Team Workspace', 'plan' => 'Plus', 'category' => 'productivity', 'status' => 'cancelled'],
                ],
            ],
        ];

        foreach ($vendors as $vendorData) {
            $services = $vendorData['services'];
            unset($vendorData['services']);

            $vendor = Vendor::query()->create($vendorData);

            foreach ($services as $serviceData) {
                $vendor->services()->create($serviceData);
            }
        }
    }

    private function syncAccountBalances(): void
    {
        $accounts = Account::query()->get();

        foreach ($accounts as $account) {
            $incoming = (float) Transaction::query()
                ->where('account_id', $account->id)
                ->where('status', 'completed')
                ->where('direction', 'in')
                ->sum('amount');

            $outgoing = (float) Transaction::query()
                ->where('account_id', $account->id)
                ->where('status', 'completed')
                ->where('direction', 'out')
                ->sum('amount');

            $account->update([
                'current_balance' => round(((float) $account->opening_balance + $incoming - $outgoing), 2),
            ]);
        }
    }
}
