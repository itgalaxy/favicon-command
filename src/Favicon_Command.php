<?php

class Favicon_Command extends WP_CLI_Command
{
    /*
     * Update a current favicon. Overwrite favicon that already exist.
     *
     * ## OPTIONS
     *
     * [<file>]
     * : Read favicon image from <file>.
     *
     */
    public function update($args, $assoc_args)
    {
        include_once ABSPATH . '/wp-admin/includes/image.php';
        include_once ABSPATH . '/wp-admin/includes/class-wp-site-icon.php';

        $file = $args[0];

        if (empty($file)) {
            WP_CLI::error('Require file parameter.');
        }

        WP_CLI::log('Importing temprorary favicon image file...');
        $response = WP_CLI::launch_self(
            'media import --porcelain',
            array($file),
            array(),
            false,
            true
        );

        if (!empty($response->stderr)) {
            WP_CLI::error(str_replace('Error: ', '', $response->stderr));
        }

        $temprorary_attachment_id = (int) $response->stdout;

        WP_CLI::log(sprintf(
            "Imported temprorary favicon image '%s' as attachment ID %d.",
            $file,
            $temprorary_attachment_id
        ));

        // Skip creating a new attachment if the attachment is a Site Icon.
        // if (get_post_meta($attachment_id, '_wp_attachment_context', true) == 'site-icon') {
        //    // Maybe regeneration all sizes
        //    update_option('site_icon', $attachment_id);
        //
        //    return;
        // }

        $current_site_icon_attachment_id = (int) get_option('site_icon');

        if ($current_site_icon_attachment_id > 0) {
            WP_CLI::log('Favicon already exists. Deleting old favicon...');
            wp_delete_attachment($current_site_icon_attachment_id, true);
        }

        WP_CLI::log('Generating favicon images...');

        $data = null;

        if (!empty($cropDetails)) {
            $data = array_map('absint', $cropDetails);
        } else {
            $image_src = wp_get_attachment_image_src($temprorary_attachment_id, 'full');
            $dst_width = 512;
            $dst_height = 512;

            if ($image_src[1] < 512) {
                $dst_width = $image_src[1];
            }

            if ($image_src[2] < 512) {
                $dst_height = $image_src[2];
            }

            $data = [
                'x1' => 0,
                'y1' => 0,
                'width' => $image_src[1],
                'height' => $image_src[2],
                'dst_width' => $dst_width,
                'dst_height' => $dst_height
            ];
        }

        // Warning: fast multiple execute produce multiple favicon images, but only last be site icon
        $cropped = wp_crop_image(
            $temprorary_attachment_id,
            $data['x1'],
            $data['y1'],
            $data['width'],
            $data['height'],
            $data['dst_width'],
            $data['dst_height']
        );

        if (!$cropped || is_wp_error($cropped)) {
            WP_CLI::error('Favicon image could not be processed.');
        }

        $wp_site_icon = new WP_Site_Icon();

        // This filter is documented in wp-admin/custom-header.php
        $cropped = apply_filters('wp_create_file_in_uploads', $cropped, $temprorary_attachment_id); // For replication.
        $object = $wp_site_icon->create_attachment_object($cropped, $temprorary_attachment_id);

        unset($object['ID']);

        // Update the attachment.
        add_filter('intermediate_image_sizes_advanced', array($wp_site_icon, 'additional_sizes'));

        $attachment_id = $wp_site_icon->insert_attachment($object, $cropped);

        remove_filter('intermediate_image_sizes_advanced', array($wp_site_icon, 'additional_sizes'));

        WP_CLI::log('Favicon images are generated.');
        WP_CLI::log('Updating favicon option...');

        update_option('site_icon', $attachment_id);

        WP_CLI::log('Favicon option are updated.');
        WP_CLI::log('Deleting temprorary favicon image file...');

        $response = WP_CLI::launch_self(
            'post delete --force',
            array($temprorary_attachment_id),
            array(),
            false,
            true
        );

        if (!empty($response->stderr)) {
            WP_CLI::error(str_replace('Error: ', '', $response->stderr));
        }

        WP_CLI::log(sprintf(
            "Imported temprorary favicon image '%s' as attachment ID %d are deleted.",
            $file,
            $temprorary_attachment_id
        ));

        WP_CLI::success('Favicon updated.');
    }
}
