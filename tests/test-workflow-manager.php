<?php
class WorkflowManagerTriggerTest extends WP_UnitTestCase {
    protected function setUp(): void {
        parent::setUp();
        $ref = new ReflectionClass('\\Gm2\\Gm2_Workflow_Manager');
        $prop = $ref->getProperty('triggers');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    public function test_publish_trigger_executes_actions() {
        $called = false;
        \Gm2\Gm2_Workflow_Manager::register_trigger('publish', [
            ['type' => 'recalculate_field', 'callback' => function() use (&$called) { $called = true; }]
        ]);
        \Gm2\Gm2_Workflow_Manager::on_status_change('publish', 'draft', (object)['ID' => 1]);
        $this->assertTrue($called);
    }

    public function test_term_assign_trigger_executes_actions() {
        $called = false;
        \Gm2\Gm2_Workflow_Manager::register_trigger('term_assign', [
            ['type' => 'recalculate_field', 'callback' => function() use (&$called) { $called = true; }]
        ]);
        \Gm2\Gm2_Workflow_Manager::on_term_assignment(1, ['a'], [], 'category', false, []);
        $this->assertTrue($called);
    }

    public function test_field_change_trigger_executes_actions() {
        $called = false;
        \Gm2\Gm2_Workflow_Manager::register_trigger('field_change', [
            ['type' => 'recalculate_field', 'callback' => function() use (&$called) { $called = true; }]
        ]);
        \Gm2\Gm2_Workflow_Manager::on_meta_update(1, 1, 'meta', 'value');
        $this->assertTrue($called);
    }
}
