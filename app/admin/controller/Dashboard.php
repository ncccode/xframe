<?php

namespace app\admin\controller;

use support\Response;
use app\common\controller\Admin;

class Dashboard extends Admin
{
    public function initialize(): void
    {
        parent::initialize();
    }

    public function index(): Response
    {
        return $this->success('', [
            'remark' => \get_route_remark()
        ]);
    }
}
