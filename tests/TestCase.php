<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Staff now work in three roles. A few editorial tests still need a person who may only draft and a person who may only review
     * (the proposal and second-person rules in the code), so those two permission sets exist here as stand-in roles for tests only.
     */
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sas.staff_roles' => array_merge(config('sas.staff_roles'), ['editor', 'reviewer']),
            'sas.permissions.editor' => ['content.draft', 'import.stage', 'articles.manage', 'taxonomy.manage', 'corrections.manage'],
            'sas.permissions.reviewer' => ['content.draft', 'content.review', 'content.publish', 'articles.manage', 'taxonomy.manage', 'corrections.manage'],
        ]);
    }
}
