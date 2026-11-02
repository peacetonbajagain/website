<?php

namespace Travo\FrontEnd\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use Travo\Admin\Models\PostCategoryModel;
use Travo\FrontEnd\Traits\CacheTrait;

class PageController extends BaseController
{
    use CacheTrait;

    public function index($slug)
    {
        $this->serveStaticPage();

        $db = \Config\Database::connect();
        $page = $db->table('pages')->where('slug', $slug)->where('status', 1)->get()->getRowArray();

        if (!$page) {
            throw PageNotFoundException::forPageNotFound('Page not found.');
        }

        $data = [
            'page_title' => $page['meta_title'] ?: $page['title'],
            'meta_desc'  => $page['meta_desc'],
            'page'       => $page,
        ];

        $output = view('Travo\FrontEnd\Views\Page\show', $data);
        $this->storeStaticPage($output);
        return $output;
    }

    public function advertisement()
    {
        $this->serveStaticPage();

        $catModel = new PostCategoryModel();
        $allCats  = $catModel->where('status', 1)->orderBy('sort_order', 'ASC')->orderBy('title', 'ASC')->findAll();

        $data = [
            'page_title' => 'Advertise With TheNosePad — Reach Engaged Eyewear Wearers',
            'meta_desc'  => 'Promote your eyewear brands, anti-fog solutions, and accessories to dedicated spectacles wearers worldwide.',
            'allCats'    => $allCats,
            'positions'  => [
                [
                    'key'       => 'header_main',
                    'name'      => 'Header Premium Banner',
                    'dims'      => '728 × 90 px (Desktop) / 320 × 100 px (Mobile)',
                    'desc'      => 'Maximum visibility placement alongside top navigation.',
                    'badge'     => 'High CTR',
                    'bg_color'  => 'from-blue-500/10 to-cyan-500/5 border-blue-200',
                    'tag_color' => 'bg-blue-100 text-blue-800'
                ],
                [
                    'key'       => 'home_above_hero',
                    'name'      => 'Homepage Leaderboard',
                    'dims'      => '970 × 90 px / 728 × 90 px',
                    'desc'      => 'Full-width banner above the daily LensWar arena.',
                    'badge'     => 'Prime Slot',
                    'bg_color'  => 'from-cyan-500/10 to-indigo-500/5 border-cyan-200',
                    'tag_color' => 'bg-cyan-100 text-cyan-800'
                ],
            ]
        ];

        $output = view('Travo\FrontEnd\Views\Page\show', [
            'page' => [
                'title' => $data['page_title'],
                'content' => '<p>' . esc($data['meta_desc']) . '</p>',
            ],
        ]);
        $this->storeStaticPage($output);
        return $output;
    }

    public function inquireAdvertisement()
    {
        return redirect()->to(base_url('advertisement'))->with('success', 'Thank you for your inquiry! Our partnership team will be in touch shortly.');
    }

    public function sendContact()
    {
        return redirect()->to(base_url('contact-us'))->with('success', 'Thank you for reaching out! TheNosePad team has received your message.');
    }
}
