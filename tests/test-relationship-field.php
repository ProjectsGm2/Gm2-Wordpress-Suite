<?php
class RelationshipFieldTest extends WP_UnitTestCase {
    public function test_two_way_user_relationship() {
        $u1 = self::factory()->user->create();
        $u2 = self::factory()->user->create();
        $field = new GM2_Field_Relationship('friends', array('relationship_type' => 'user', 'sync' => 'two-way'));
        $field->save($u1, array($u2), 'user');
        $this->assertEquals(array($u2), get_user_meta($u1, 'friends', true));
        $this->assertEquals(array($u1), get_user_meta($u2, 'friends', true));
    }

    public function test_one_way_post_relationship() {
        $p1 = self::factory()->post->create();
        $p2 = self::factory()->post->create();
        $field = new GM2_Field_Relationship('related', array('relationship_type' => 'post', 'sync' => 'one-way'));
        $field->save($p1, array($p2), 'post');
        $this->assertEquals(array($p2), get_post_meta($p1, 'related', true));
        $this->assertEquals(array($p1), get_post_meta($p2, 'related', true));
        // remove relation from p1; p2 should still reference p1
        $field->save($p1, array(), 'post');
        $this->assertEquals(array(), get_post_meta($p1, 'related', true));
        $this->assertEquals(array($p1), get_post_meta($p2, 'related', true));
    }

    public function test_role_relationship_two_way() {
        $p1 = self::factory()->post->create();
        $field = new GM2_Field_Relationship('roles', array('relationship_type' => 'role', 'sync' => 'two-way'));
        $field->save($p1, array('editor'), 'post');
        $this->assertEquals(array('editor'), get_post_meta($p1, 'roles', true));
        $this->assertEquals(array($p1), get_option('roles_role_editor'));
        delete_option('roles_role_editor');
    }
}
