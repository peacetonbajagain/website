<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TravoStarterSeeder extends Seeder
{
    public function run(): void
    {
        $this->db->transStart();

        if (setting('Validation.login') === null) {
            setting('Validation.login', [
                'email' => [
                    'label' => 'Auth.email',
                    'rules' => ['permit_empty', 'valid_email', 'max_length[254]'],
                ],
                'username' => [
                    'label' => 'Auth.username',
                    'rules' => ['permit_empty', 'min_length[3]', 'max_length[30]', 'regex_match[/\A[a-zA-Z0-9\.]+\z/]'],
                ],
                'password' => [
                    'label' => 'Auth.password',
                    'rules' => ['required', \CodeIgniter\Shield\Authentication\Passwords::getMaxLengthRule()],
                ],
            ]);
        }

        $categories = [
            'Local' => 'local',
            'Business' => 'business',
            'Technology' => 'technology',
            'Environment' => 'environment',
            'Culture' => 'culture',
        ];
        $categoryIds = [];

        foreach ($categories as $title => $slug) {
            $category = $this->db->table('blog_categories')
                ->select('id')
                ->where('slug', $slug)
                ->get()
                ->getRowArray();

            if ($category === null) {
                $this->db->table('blog_categories')->insert([
                    'title' => $title,
                    'slug' => $slug,
                    'sort_order' => \count($categoryIds),
                    'on_menu' => 1,
                    'status' => 1,
                ]);
                $categoryIds[$slug] = $this->db->insertID();
            } else {
                $categoryIds[$slug] = (int) $category['id'];
            }
        }

        $demoNotice = '<p><strong>Starter demo copy:</strong> This is a general editorial example, not reported news. Replace it with reviewed reporting before publishing.</p>';
        $posts = [
            [
                'title' => 'Sample: Three questions every local budget should answer',
                'slug' => 'sample-local-budget-questions',
                'category' => 'local',
                'excerpt' => 'A practical guide to reading public budgets, tracking priorities, and asking better questions at community meetings.',
                'thumbnail' => 'photo-1486406146926-c627a92ad1ab',
                'featured' => 1,
                'content' => $demoNotice . '<p>A local budget is more than a list of totals. It is a public plan for the services, repairs, and projects a community will rely on throughout the year.</p><h2>Follow the priorities</h2><p>Start by comparing the largest allocations with the needs residents raise most often. Ask what changed from the previous plan, which projects have a timeline, and how progress will be reported.</p><h2>Look for the details</h2><ul><li>Are ongoing costs separated from one-time projects?</li><li>Are deadlines and responsible departments named?</li><li>Can residents find updates without attending a meeting?</li></ul><p>Clear answers make it easier for everyone to follow the work and take part in decisions that shape their neighborhood.</p>',
            ],
            [
                'title' => 'Sample: Digital payments work best when small businesses have a choice',
                'slug' => 'sample-small-business-digital-payments',
                'category' => 'business',
                'excerpt' => 'Convenience matters, but fees, reliability, and access to support belong in every small business payment decision.',
                'thumbnail' => 'photo-1556742049-0cfed4f6a45d',
                'featured' => 0,
                'content' => $demoNotice . '<p>Digital payment tools can make checkout simpler for customers and staff. The right setup, however, depends on how a business operates day to day.</p><h2>Compare the full cost</h2><p>Before choosing a provider, review transaction fees, settlement timing, equipment costs, and the process for resolving a disputed payment. A low advertised rate does not always mean a lower total cost.</p><h2>Keep a backup plan</h2><p>Connectivity problems and service interruptions happen. Businesses should know how to record a sale securely and reconcile it later, while keeping customer information protected.</p><p>Choice and clear terms help owners adopt useful tools without giving up control of their operations.</p>',
            ],
            [
                'title' => 'Sample: A practical checklist for responsible AI in a newsroom',
                'slug' => 'sample-responsible-ai-newsroom-checklist',
                'category' => 'technology',
                'excerpt' => 'Useful newsroom AI policies start with human accountability, source protection, and clear disclosure rules.',
                'thumbnail' => 'photo-1518770660439-4636190af475',
                'featured' => 0,
                'content' => $demoNotice . '<p>AI tools can assist with routine tasks, but editorial responsibility stays with people. A newsroom policy should explain what tools may do and where human review is required.</p><h2>Set clear boundaries</h2><ul><li>Verify every factual claim against reliable source material.</li><li>Never upload confidential sources or unpublished sensitive material to an unapproved service.</li><li>Disclose generated or altered media clearly to readers.</li><li>Keep an editor accountable for the final work.</li></ul><p>These guardrails do not replace good reporting. They help teams use new tools without weakening trust, accuracy, or source safety.</p>',
            ],
            [
                'title' => 'Sample: Shade, water, and access belong in a heat-ready street plan',
                'slug' => 'sample-heat-ready-streets',
                'category' => 'environment',
                'excerpt' => 'Street-level planning can make hot days safer when it considers shade, drinking water, transit stops, and people most at risk.',
                'thumbnail' => 'photo-1470071459604-3b5ec3a7fe05',
                'featured' => 0,
                'content' => $demoNotice . '<p>People experience hot weather differently depending on their route, access to cooling, and time spent outdoors. A useful neighborhood plan starts with those everyday conditions.</p><h2>Design for real journeys</h2><p>Map walking routes to transit, schools, clinics, and public services. Then identify where shade, drinking water, seating, and clear heat-safety information would help most.</p><h2>Measure what changes</h2><p>Publish project locations, timelines, and maintenance plans so residents can see whether improvements are reaching the places that need them.</p><p>Small, well-maintained interventions can make public spaces easier to use during extreme heat.</p>',
            ],
            [
                'title' => 'Sample: Why neighborhood libraries still matter',
                'slug' => 'sample-neighborhood-libraries',
                'category' => 'culture',
                'excerpt' => 'Libraries offer more than shelves: they are shared places to learn, find reliable information, and connect with a community.',
                'thumbnail' => 'photo-1507842217343-583bb7270b66',
                'featured' => 0,
                'content' => $demoNotice . '<p>A neighborhood library gives people a place to read, study, use shared resources, and ask for help finding information. Its value is often measured in everyday access as much as in books borrowed.</p><h2>A space for different needs</h2><p>Students may need a quiet desk. A job seeker may need internet access. Families may look for a welcoming activity close to home. Well-planned library services make room for all of these uses.</p><p>Listening to visitors and sharing opening hours, programs, and available services helps a library stay useful to the community around it.</p>',
            ],
        ];

        foreach ($posts as $index => $post) {
            $existing = $this->db->table('posts')
                ->select('id')
                ->where('slug', $post['slug'])
                ->get()
                ->getRowArray();

            if ($existing !== null) {
                continue;
            }

            $this->db->table('posts')->insert([
                'title' => $post['title'],
                'slug' => $post['slug'],
                'content' => $post['content'],
                'excerpt' => $post['excerpt'],
                'thumbnail' => 'https://images.unsplash.com/' . $post['thumbnail'] . '?auto=format&fit=crop&w=1200&q=80',
                'is_featured' => $post['featured'],
                'is_breaking_news' => 0,
                'is_trending' => 0,
                'view_count' => 0,
                'author_name' => 'Samachar Desk (Demo)',
                'status' => 1,
                'published_at' => date('Y-m-d H:i:s', time() - ($index + 1) * 3600),
            ]);

            $this->db->table('post_categories')->insert([
                'post_id' => $this->db->insertID(),
                'category_id' => $categoryIds[$post['category']],
            ]);
        }

        $pages = [
            'about' => [
                'title' => 'About Samachar',
                'content' => '<p>This starter page introduces the publication. Replace this copy with your newsroom mission, ownership details, editorial standards, and the communities you serve.</p><h2>Our approach</h2><p>Explain how your team reports, verifies, corrects, and updates its work. Readers should be able to understand who is responsible for the journalism they read.</p>',
                'meta_desc' => 'Learn about the Samachar newsroom, its mission, and editorial standards.',
            ],
            'contact' => [
                'title' => 'Contact the newsroom',
                'content' => '<p>This is a contact-page starter. Add a monitored newsroom email address, response expectations, and any verified postal or phone details before launch.</p><h2>What to include</h2><ul><li>News tips and corrections contact</li><li>Advertising or partnership contact, if applicable</li><li>Expected response times</li></ul><p>Do not publish personal contact details unless the person has agreed to share them.</p>',
                'meta_desc' => 'Contact the Samachar newsroom with news tips, corrections, or general questions.',
            ],
            'privacy' => [
                'title' => 'Privacy notice',
                'content' => '<p>This is a starter outline, not legal advice or a complete privacy policy. Before launch, replace it with a notice reviewed for your location and the services this site actually uses.</p><h2>Describe your practices</h2><p>Explain what information is collected through comments, account sign-in, server logs, cookies, analytics, and embedded services; why it is used; how long it is retained; and how readers can contact you about their data.</p><h2>Keep it accurate</h2><p>Remove any section that does not reflect your actual practices, and update this page whenever those practices change.</p>',
                'meta_desc' => 'Starter privacy notice for Samachar. Review and customize before launch.',
            ],
        ];

        foreach ($pages as $slug => $page) {
            $exists = $this->db->table('pages')
                ->select('id')
                ->where('slug', $slug)
                ->get()
                ->getRowArray();

            if ($exists !== null) {
                continue;
            }

            $this->db->table('pages')->insert([
                'title' => $page['title'],
                'slug' => $slug,
                'content' => $page['content'],
                'meta_title' => $page['title'],
                'meta_desc' => $page['meta_desc'],
                'status' => 1,
            ]);
        }

        $this->db->transComplete();
    }
}