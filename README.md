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
- Opsi layout repeater: `default`, `simple`, `grid`.

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

Dari `includes/helpers/relational-helpers.php`:
- `cff_get_relational_post()`
- `cff_get_relational_posts()`
- `cff_get_relational_term()`
- `cff_get_relational_terms()`
- `cff_get_relational_user()`
- `cff_get_relational_users()`

### 1.7 Contoh Penggunaan

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

### 1.8 Alur Import/Export (Disarankan)

1. Export dari site sumber (`Tools > Export`).
2. Import JSON di site tujuan (`Tools > Import`).
3. Re-save permalink jika struktur CPT/taxonomy berubah.
4. Verifikasi location rules dan render field di editor.
5. Jika migrasi value ACF, gunakan alur export SQL dan eksekusi dengan aman.

### 1.9 Troubleshooting

- URL jadi 404 setelah ubah CPT/taxonomy: re-save `Settings > Permalinks`.
- Field group tidak muncul: cek location rules dan kecocokan post type/template.
- Media picker bermasalah: cek konflik script/style dari plugin optimasi.
- Hasil migrasi ACF belum lengkap: tinjau field type ACF yang tidak punya mapping langsung lalu sesuaikan manual.

### 1.10 Versi

- Versi plugin saat ini: `0.15.2`

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
- Repeater layout variants: `default`, `simple`, `grid`.

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

From `includes/helpers/relational-helpers.php`:
- `cff_get_relational_post()`
- `cff_get_relational_posts()`
- `cff_get_relational_term()`
- `cff_get_relational_terms()`
- `cff_get_relational_user()`
- `cff_get_relational_users()`

### 2.7 Example Usage

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

### 2.8 Import/Export Workflow (Recommended)

1. Export from source site (`Tools > Export`).
2. Import JSON in target site (`Tools > Import`).
3. Re-save permalinks if CPT/taxonomy structure changed.
4. Verify location rules and field rendering in editor.
5. If migrating ACF values, run SQL export workflow and apply SQL safely.

### 2.9 Troubleshooting

- URLs return 404 after CPT/taxonomy changes: re-save `Settings > Permalinks`.
- Field group not showing: verify location rules and post type/template match.
- Media picker not behaving as expected: check script/style conflicts from optimization plugins.
- ACF migration is partially mapped: review unsupported ACF field types and adjust manually.

### 2.10 Version

- Current plugin version: `0.15.2`
