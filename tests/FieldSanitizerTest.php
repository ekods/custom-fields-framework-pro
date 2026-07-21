<?php
use CFF\Field_Sanitizer;
use PHPUnit\Framework\TestCase;

final class FieldSanitizerTest extends TestCase {
  private $sanitizer;

  protected function setUp(): void {
    $this->sanitizer = new Field_Sanitizer();
  }

  public function test_number_rejects_non_numeric_values(): void {
    $this->assertSame(12.5, $this->sanitizer->sanitize(['type' => 'number'], '12.5'));
    $this->assertNull($this->sanitizer->sanitize(['type' => 'number'], 'nope'));
  }

  public function test_choice_is_limited_to_configured_values(): void {
    $field = [
      'type' => 'choice',
      'choices' => [['label' => 'Published', 'value' => 'published']],
    ];
    $this->assertSame('published', $this->sanitizer->sanitize($field, 'published'));
    $this->assertSame('', $this->sanitizer->sanitize($field, 'administrator'));
  }

  public function test_relational_multiple_removes_empty_marker_and_duplicates(): void {
    $field = ['type' => 'relational', 'relational_multiple' => true];
    $this->assertSame([12, 19], $this->sanitizer->sanitize($field, ['__cff_rel_empty__', '12', '19', '12']));
  }

  public function test_empty_gallery_ignores_presence_marker(): void {
    $field = ['type' => 'gallery'];
    $this->assertSame([], $this->sanitizer->sanitize($field, ['__cff_present' => '1']));
    $this->assertSame([12, 19], $this->sanitizer->sanitize($field, [
      '__cff_present' => '1',
      '12',
      ['id' => '19'],
      '12',
    ]));
  }

  public function test_group_only_accepts_defined_subfields(): void {
    $field = [
      'type' => 'group',
      'sub_fields' => [['name' => 'title', 'type' => 'text']],
    ];
    $this->assertSame(['title' => 'Hello'], $this->sanitizer->sanitize($field, [
      'title' => '<b>Hello</b>',
      'unexpected' => 'drop me',
    ]));
  }

  public function test_date_requires_an_exact_valid_format(): void {
    $field = ['type' => 'date_picker'];
    $this->assertSame('2026-06-21', $this->sanitizer->sanitize($field, '2026-06-21'));
    $this->assertSame('', $this->sanitizer->sanitize($field, '2026-02-30'));
  }

  public function test_link_target_is_allowlisted(): void {
    $field = ['type' => 'link'];
    $value = $this->sanitizer->sanitize($field, [
      'url' => 'https://example.com',
      'title' => 'Example',
      'target' => 'javascript:alert(1)',
    ]);
    $this->assertSame('', $value['target']);
  }

  public function test_link_preserves_internal_mode_metadata(): void {
    $field = ['type' => 'link'];
    $value = $this->sanitizer->sanitize($field, [
      'mode' => 'internal',
      'url' => '',
      'title' => 'Internal Page',
      'target' => '_blank',
      'internal_id' => '42',
      'post_type_filter' => 'page',
      'parameter' => '?ref=hero',
      'hash' => '#cta',
    ]);

    $this->assertSame('internal', $value['mode']);
    $this->assertSame('https://example.test/post-42/?ref=hero#cta', $value['url']);
    $this->assertSame(42, $value['internal_id']);
    $this->assertSame('page', $value['post_type_filter']);
    $this->assertSame('ref=hero', $value['parameter']);
    $this->assertSame('cta', $value['hash']);
    $this->assertSame('_blank', $value['target']);
  }
}
