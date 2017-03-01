Feature: Manage favicon in a WordPress using wp-cli

    Background:
        Given a WP install

    Scenario: Scheduling and then deleting an event
        When I run `wp cron event schedule wp_cli_test_event_1 '+1 hour 5 minutes' --apple=banana`
        Then STDOUT should contain:
            """
            Success: Scheduled event with hook 'wp_cli_test_event_1'
            """
