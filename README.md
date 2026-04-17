# Custom Fields Framework Pro

## 1. Dokumentasi Bahasa Indonesia

### 1.1 Ringkasan

Custom Fields Framework Pro adalah plugin WordPress untuk membangun sistem konten terstruktur tanpa perlu menulis metabox manual berulang.

Kemampuan utama:
- Builder Field Group visual
- Manajemen Custom Post Type (CPT) dinamis
- Manajemen Taxonomy dinamis
- Sistem reorder (post, term, group)
- Dukungan Polylang untuk label/slug
- Utilitas Import/Export dan migrasi ACF
- Helper frontend bergaya ACF
- REST API field `cff` otomatis per post type yang mendukung REST

### 1.2 Fitur Utama

#### Field Groups
- Buat field group di `cff_group`.
- Tentukan lokasi group berdasarkan rule:
  - `post_type`
  - `page_template`
  - post tertentu
  - page tertentu
- Atur presentasi:
  - style (`standard`, `seamless`)
  - posisi metabox (`high`, `normal`, `side`)
  - posisi label (`top`, `left`)
  - posisi instruksi (`below_labels`, `below_fields`)
  - urutan tampil antar group
  - hide-on-screen untuk panel default WordPress

#### Jenis Field yang Didukung
- `text`
- `number`
- `textarea`
- `wysiwyg`
- `color`
- `url`
- `link`
- `embed`
- `choice` (select, checkbox, radio, button_group, true_false)
- `relational` (post/page/custom post type/taxonomy/user)
- `date_picker`
- `datetime_picker`
- `checkbox`
- `image`
- `file`
- `repeater`
- `group`
- `flexible`

#### Struktur Bersarang (Nested)
- Repeater dengan sub field.
- Group dengan sub field.
- Flexible Content dengan layout dan field per layout.
- Opsi layout repeater: `default`, `simple`, `grid`, `row`.
- Opsi lanjutan repeater:
  - `min` dan `max` rows
  - clone row
  - collapse row default
  - row label dinamis dari nama sub field tertentu

#### Dynamic Post Types
- Buat, edit, duplikat, hapus CPT dari admin.
- Pengaturan:
  - label singular/plural
  - slug
  - public, archive, REST API
  - supports
  - taxonomy terkait
  - menu icon dan menu position
  - kolom thumbnail di daftar admin
  - opsi nonaktifkan single view
- Slug bisa otomatis dari label plural jika slug kosong.

#### Dynamic Taxonomies
- Buat, edit, hapus taxonomy.
- Pengaturan:
  - label singular/plural
  - slug
  - hierarchical/public/REST API
  - target post type
- Mendukung i18n label/slug saat Polylang aktif.

#### Sistem Reorder
- Reorder post/CPT dengan drag-and-drop (`menu_order`).
- Reorder term taxonomy dengan term meta (`cffp_term_order`).
- Reorder field group.
- Dropdown taxonomy pada reorder menampilkan konteks post type agar lebih jelas.

#### Tools dan Portabilitas Data
- Export JSON:
  - Post Types
  - Taxonomies
  - Field Groups
  - bisa pilih Field Group tertentu
- Import JSON dari format export plugin ini.
- Import dasar ACF JSON (mapping basic).
- Migrasi field group dari ACF.
- Export SQL untuk memindahkan value ACF ke key `_cff_`.

#### Integrasi Polylang
- Filter rewrite slug untuk CPT/taxonomy per bahasa.
- Perbaikan language-link untuk archive dan taxonomy.
- Mendukung field i18n singular/plural/slug.

#### Resolusi Template
- Template single berbasis slug: `single-{slug}.php`
- Template archive berbasis slug: `archive-{slug}.php`

#### Penyimpanan Data
- Nilai field disimpan di post meta dengan prefix `_cff_`.
- Data nested disanitasi sebelum disimpan.
- Field media dapat menyimpan ID dan konteks URL.

### 1.3 Struktur Menu Admin

- `Custom Fields`
- `Field Groups`
- `Post Types`
- `Taxonomies`
- `Reorder`
- `Tools`
- `Documentation`

Plugin juga membuat submenu reorder per post type secara dinamis.

### 1.4 Instalasi

1. Upload plugin ke `wp-content/plugins/custom-fields-framework-pro`.
2. Aktifkan plugin dari dashboard WordPress.
3. Buka menu `Custom Fields`.
4. Buat Field Group lalu atur location rules.
5. Buka editor post/page/CPT yang sesuai dan isi field.

### 1.5 Quick Start

1. Buat Field Group baru.
2. Tambahkan field (`text`, `image`, `repeater`, dll).
3. Atur location rule ke post type target.
4. Simpan group.
5. Edit konten target dan isi nilai field.
6. Ambil nilai di theme lewat helper function.

### 1.6 API Helper Frontend

Dari `includes/helpers/acf-compat.php`:
- `get_field($selector, $post_id = false, $format_value = true)`
- `the_field($selector, $post_id = false)`
- `have_rows($selector, $post_id = false)`
- `the_row()`
- `get_row_layout()`
- `get_sub_field($selector, $format_value = true)`
- `the_sub_field($selector)`
- `\CFF\cff_get_ordered_fields($post_id, $group_id, $include_values = false, $format_value = true)`
- `\CFF\cff_render_ordered_fields($post_id, $group_id)`
- `\CFF\cff_get_global_field($selector, $format_value = true)`
- `\CFF\cff_the_global_field($selector)`
- `\CFF\cff_get_ordered_field_names($post_id, $candidate_names = [], $group_id = 0)`

Dari `includes/helpers/relational-helpers.php`:
- `cff_get_relational_post()`
- `cff_get_relational_posts()`
- `cff_get_relational_term()`
- `cff_get_relational_terms()`
- `cff_get_relational_user()`
- `cff_get_relational_users()`

Dari `includes/helpers/frontend-helpers.php`:
- `\CFF\cff_get_value($field_name, $post_id = 0, $default = null, $format_value = true)`
- `\CFF\cff_has_value($field_name, $post_id = 0)`
- `\CFF\cff_get_text($field_name, $post_id = 0, $default = '')`
- `\CFF\cff_get_image_url($field_name, $post_id = 0, $size = 'full', $default = '')`
- `\CFF\cff_get_file_url($field_name, $post_id = 0, $default = '')`
- `\CFF\cff_get_repeater_rows($field_name, $post_id = 0)`
- `\CFF\cff_get_group_value($group_field, $sub_field, $post_id = 0, $default = null)`

Shortcode frontend:
- `[cff_value name="headline"]`
- `[cff_field name="headline"]`
- `[cff_item name="headline"]`
- `[cff_loop group_id="123"]...[/cff_loop]`
- `[cff_items group_id="123"]...[/cff_items]`
- `[cff_loop candidates="gallery_1,huge_image,detail_2"]...[/cff_loop]`

### 1.7 Contoh Penggunaan (Frontend Helper)

```php
<?php
$subtitle = get_field('subtitle');
if ($subtitle) {
  echo '<p>' . esc_html($subtitle) . '</p>';
}

if (have_rows('sections')) {
  while (have_rows('sections')) {
    the_row();
    $heading = get_sub_field('heading');
    echo '<h3>' . esc_html($heading) . '</h3>';
  }
}
```

Contoh helper baru:

```php
<?php
use function CFF\cff_get_text;
use function CFF\cff_get_image_url;
use function CFF\cff_get_repeater_rows;

$headline = cff_get_text('headline');
$hero_image = cff_get_image_url('hero_image', 0, 'large');
$faq_rows = cff_get_repeater_rows('faq_items');
```

Contoh shortcode single field:

```text
[cff_value name="headline"]
[cff_field name="subtitle" default="Tidak ada subtitle"]
[cff_item name="subtitle" default="Tidak ada subtitle"]
```

Contoh shortcode loop untuk render urutan field hasil reorder:

```text
[cff_items group_id="123"]
  <section class="section-[cff_item key='name']">
    <h2>[cff_item key='label']</h2>
    [cff_item]
  </section>
[/cff_items]
```

Atau tanpa `group_id`, pakai kandidat nama field dari template:

```text
[cff_items candidates="gallery_1,huge_image,gallery_2_grid,gallery_2,detail_2"]
  <section class="section-[cff_item key='name']">
    [cff_item]
  </section>
[/cff_items]
```

Catatan:
- `[cff_field]` di dalam `[cff_loop]` default mengambil `value`.
- `[cff_item]` adalah alias yang sama untuk `[cff_field]`.
- `[cff_items]` adalah alias yang sama untuk `[cff_loop]`.
- Gunakan `key="label"`, `key="name"`, atau `key="type"` untuk properti field aktif.
- Untuk cross-page, gunakan `post_id` atau `page_id`.
- Untuk Polylang, gunakan `lang="en"` atau biarkan kosong agar mengikuti bahasa aktif.
- `[cff_field name="headline"]`, `[cff_item name="headline"]`, atau `[cff_value name="headline"]` bisa dipakai langsung tanpa loop.

Contoh cross-page + bilingual Polylang:

```text
[cff_value name="headline" page_id="42"]
[cff_item name="headline" page_id="42" lang="en"]

[cff_items page_id="42" group_id="123"]
  <section>[cff_item]</section>
[/cff_items]
```

### 1.7.1 Rekomendasi Optimasi Frontend

- Untuk field tunggal, utamakan helper langsung seperti `cff_get_text()` atau shortcode `[cff_value]`; jangan scan semua group jika tidak perlu.
- Untuk layout yang mengikuti reorder, pakai `[cff_loop group_id="..."]` bila `group_id` sudah diketahui. Ini lebih ringan dibanding auto-detect.
- Plugin sekarang memakai cache per-request untuk:
  - post meta `_cff_*`
  - `_cff_settings` per field group
  - daftar `cff_group`
- Hindari memanggil loop reorder berulang untuk post yang sama dalam satu template. Ambil sekali, lalu render.

### 1.8 REST API (`cff`)

- Field REST `cff` otomatis tersedia pada post type yang `show_in_rest = true`.
- Endpoint mengikuti post type REST default WordPress.
- Secara default payload bisa dibaca dan ditulis (jika user punya izin edit post).

Contoh baca data:

```http
GET /wp-json/wp/v2/posts/123?_fields=id,title,cff
```

Contoh update data:

```http
POST /wp-json/wp/v2/posts/123
Content-Type: application/json

{
  "cff": {
    "headline": "Updated headline",
    "faq_items": [
      { "question": "Q1", "answer": "A1" },
      { "question": "Q2", "answer": "A2" }
    ]
  }
}
```

Filter yang tersedia:
- `cff_rest_fields_writable` untuk mengunci write per post type.
- `cff_rest_fields_format_value` untuk mengatur format output value.
- `cff_rest_fields_readonly` untuk menandai field tertentu readonly saat update.

### 1.9 Gutenberg Sidebar (Opsional)

- Buka `Custom Fields > Global Settings`.
- Pada panel **Editor UI Settings**, aktifkan:
  - `Enable CFF panels in Gutenberg document sidebar`.
- Toggle ini menyimpan option `cffp_block_sidebar_enabled`.
- Saat aktif, metabox CFF dipindahkan ke panel sidebar Gutenberg.

Override via kode (opsional):

```php
add_filter('cff_block_sidebar_enabled', function($enabled, $screen){
  return true;
}, 10, 2);
```

### 1.10 Alur Import/Export (Disarankan)

1. Export dari site sumber (`Tools > Export`).
2. Import JSON di site tujuan (`Tools > Import`).
3. Re-save permalink jika struktur CPT/taxonomy berubah.
4. Verifikasi location rules dan render field di editor.
5. Jika migrasi value ACF, gunakan alur export SQL dan eksekusi dengan aman.

### 1.11 Troubleshooting

- URL jadi 404 setelah ubah CPT/taxonomy: re-save `Settings > Permalinks`.
- Field group tidak muncul: cek location rules dan kecocokan post type/template.
- Global Settings kosong: pastikan ada Field Group dengan rule `Options Page == Global Settings`.
- Media picker bermasalah: cek konflik script/style dari plugin optimasi.
- Hasil migrasi ACF belum lengkap: tinjau field type ACF yang tidak punya mapping langsung lalu sesuaikan manual.

### 1.12 Versi

- Versi plugin saat ini: `1.01`

---

## 2. English Documentation

### 2.1 Overview

Custom Fields Framework Pro is a WordPress plugin for building structured content systems without writing repetitive meta box code.

Core capabilities:
- Visual Field Group Builder
- Dynamic Custom Post Types (CPT)
- Dynamic Taxonomies
- Reorder engine (posts, terms, groups)
- Polylang-aware labels/slugs
- Import/Export and ACF migration utilities
- ACF-style frontend helper functions
- Automatic `cff` REST field for post types that support REST

### 2.2 Main Features

#### Field Groups
- Create field groups in `cff_group`.
- Assign groups using location rules:
  - `post_type`
  - `page_template`
  - specific post
  - specific page
- Configure presentation:
  - style (`standard`, `seamless`)
  - metabox position (`high`, `normal`, `side`)
  - label placement (`top`, `left`)
  - instruction placement (`below_labels`, `below_fields`)
  - display order between groups
  - hide-on-screen controls for native WP panels

#### Supported Field Types
- `text`
- `number`
- `textarea`
- `wysiwyg`
- `color`
- `url`
- `link`
- `embed`
- `choice` (select, checkbox, radio, button_group, true_false)
- `relational` (post/page/custom post type/taxonomy/user)
- `date_picker`
- `datetime_picker`
- `checkbox`
- `image`
- `file`
- `repeater`
- `group`
- `flexible`

#### Nested Structures
- Repeater with sub fields.
- Group with nested sub fields.
- Flexible Content with layouts and per-layout fields.
- Repeater layout variants: `default`, `simple`, `grid`, `row`.
- Advanced repeater controls:
  - `min` and `max` rows
  - row cloning
  - default-collapsed rows
  - dynamic row title from a selected sub field

#### Dynamic Post Types
- Create, edit, duplicate, delete CPTs from admin UI.
- Configure:
  - singular/plural labels
  - slug
  - public, archive, REST API
  - supports
  - assigned taxonomies
  - menu icon and menu position
  - optional list thumbnail column
  - optional block single view
- Slug can auto-generate from plural label when empty.

#### Dynamic Taxonomies
- Create, edit, delete taxonomies.
- Configure:
  - singular/plural labels
  - slug
  - hierarchical/public/REST API
  - target post types
- Supports Polylang i18n labels/slugs.

#### Reorder System
- Reorder posts/CPT entries by drag-and-drop (`menu_order`).
- Reorder taxonomy terms using term meta (`cffp_term_order`).
- Reorder field groups.
- Taxonomy reorder selector includes post type context for clearer labels.

#### Tools and Data Portability
- Export JSON:
  - Post Types
  - Taxonomies
  - Field Groups
  - selective Field Group export
- Import JSON from plugin export format.
- Basic ACF JSON field-group import mapping.
- Migrate field groups from ACF.
- Export SQL to migrate ACF values into `_cff_` keys.

#### Polylang Integration
- Translation-aware CPT and taxonomy rewrite slug filters.
- Archive and taxonomy language-link fixes.
- i18n-aware singular/plural/slug definition fields.

#### Template Resolution
- Single template lookup by configured slug: `single-{slug}.php`
- Archive template lookup by configured slug: `archive-{slug}.php`

#### Content Storage
- Field values are stored in post meta with `_cff_` prefix.
- Nested values are sanitized before save.
- Media-oriented fields can store ID and URL context.

### 2.3 Admin Menu

- `Custom Fields`
- `Field Groups`
- `Post Types`
- `Taxonomies`
- `Reorder`
- `Tools`
- `Documentation`

Additional dynamic reorder submenu pages are generated per post type.

### 2.4 Installation

1. Upload plugin to `wp-content/plugins/custom-fields-framework-pro`.
2. Activate from WordPress admin.
3. Open `Custom Fields`.
4. Create a Field Group and set location rules.
5. Open matching post/page/CPT editor and fill fields.

### 2.5 Quick Start

1. Create a new Field Group.
2. Add fields (`text`, `image`, `repeater`, etc.).
3. Set location rule to target your post type.
4. Save group.
5. Edit target content and input values.
6. Read values in theme using helper functions.

### 2.6 Frontend Helper API

From `includes/helpers/acf-compat.php`:
- `get_field($selector, $post_id = false, $format_value = true)`
- `the_field($selector, $post_id = false)`
- `have_rows($selector, $post_id = false)`
- `the_row()`
- `get_row_layout()`
- `get_sub_field($selector, $format_value = true)`
- `the_sub_field($selector)`
- `\CFF\cff_get_ordered_fields($post_id, $group_id, $include_values = false, $format_value = true)`
- `\CFF\cff_render_ordered_fields($post_id, $group_id)`
- `\CFF\cff_get_global_field($selector, $format_value = true)`
- `\CFF\cff_the_global_field($selector)`
- `\CFF\cff_get_ordered_field_names($post_id, $candidate_names = [], $group_id = 0)`

From `includes/helpers/relational-helpers.php`:
- `cff_get_relational_post()`
- `cff_get_relational_posts()`
- `cff_get_relational_term()`
- `cff_get_relational_terms()`
- `cff_get_relational_user()`
- `cff_get_relational_users()`

From `includes/helpers/frontend-helpers.php`:
- `\CFF\cff_get_value($field_name, $post_id = 0, $default = null, $format_value = true)`
- `\CFF\cff_has_value($field_name, $post_id = 0)`
- `\CFF\cff_get_text($field_name, $post_id = 0, $default = '')`
- `\CFF\cff_get_image_url($field_name, $post_id = 0, $size = 'full', $default = '')`
- `\CFF\cff_get_file_url($field_name, $post_id = 0, $default = '')`
- `\CFF\cff_get_repeater_rows($field_name, $post_id = 0)`
- `\CFF\cff_get_group_value($group_field, $sub_field, $post_id = 0, $default = null)`

### 2.7 Example Usage (Frontend Helper)

```php
<?php
$subtitle = get_field('subtitle');
if ($subtitle) {
  echo '<p>' . esc_html($subtitle) . '</p>';
}

if (have_rows('sections')) {
  while (have_rows('sections')) {
    the_row();
    $heading = get_sub_field('heading');
    echo '<h3>' . esc_html($heading) . '</h3>';
  }
}
```

New helper example:

```php
<?php
use function CFF\cff_get_text;
use function CFF\cff_get_image_url;
use function CFF\cff_get_repeater_rows;

$headline = cff_get_text('headline');
$hero_image = cff_get_image_url('hero_image', 0, 'large');
$faq_rows = cff_get_repeater_rows('faq_items');
```

### 2.8 REST API (`cff`)

- The `cff` REST field is automatically registered on post types with `show_in_rest = true`.
- It follows each post type's default WordPress REST endpoint.
- By default, payload is readable and writable (subject to `edit_post` capability).

Read example:

```http
GET /wp-json/wp/v2/posts/123?_fields=id,title,cff
```

Update example:

```http
POST /wp-json/wp/v2/posts/123
Content-Type: application/json

{
  "cff": {
    "headline": "Updated headline",
    "faq_items": [
      { "question": "Q1", "answer": "A1" },
      { "question": "Q2", "answer": "A2" }
    ]
  }
}
```

Available filters:
- `cff_rest_fields_writable` to lock write access by post type.
- `cff_rest_fields_format_value` to control formatted output value.
- `cff_rest_fields_readonly` to make specific fields readonly on update.

### 2.9 Gutenberg Sidebar (Optional)

- Open `Custom Fields > Global Settings`.
- In **Editor UI Settings**, enable:
  - `Enable CFF panels in Gutenberg document sidebar`.
- This toggle stores the `cffp_block_sidebar_enabled` option.
- When enabled, CFF metabox groups are relocated into Gutenberg sidebar panels.

Optional code override:

```php
add_filter('cff_block_sidebar_enabled', function($enabled, $screen){
  return true;
}, 10, 2);
```

### 2.10 Import/Export Workflow (Recommended)

1. Export from source site (`Tools > Export`).
2. Import JSON in target site (`Tools > Import`).
3. Re-save permalinks if CPT/taxonomy structure changed.
4. Verify location rules and field rendering in editor.
5. If migrating ACF values, run SQL export workflow and apply SQL safely.

### 2.11 Troubleshooting

- URLs return 404 after CPT/taxonomy changes: re-save `Settings > Permalinks`.
- Field group not showing: verify location rules and post type/template match.
- Global Settings is empty: make sure at least one Field Group uses `Options Page == Global Settings`.
- Media picker not behaving as expected: check script/style conflicts from optimization plugins.
- ACF migration is partially mapped: review unsupported ACF field types and adjust manually.

### 2.12 Version

- Current plugin version: `1.01`
