<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        try {
            $settings = Setting::cached()->toArray();

            $this->applyGeneralSettings($settings);
            $this->applyMailSettings($settings);
            $this->applyCaptchaSettings($settings);
            $this->applyWhatsappSettings($settings);
            $this->applyVoteSettings($settings);
            $this->applyHistorySettings($settings);
            $this->applyJsonConfig($settings, 'donate', 'donate');
            $this->applyJsonConfig($settings, 'widgets', 'widgets');
            $this->applyEventScheduleOverrides($settings);
            $this->applyJsonConfig($settings, 'ranking', 'ranking');
            $this->applyRankingSettings();
            $this->applyJsonConfig($settings, 'referral', 'global.referral');
            $this->applyJsonConfig($settings, 'tickets', 'global.tickets');
            $this->applyJsonConfig($settings, 'sliders', 'global.slider');
            $this->applyJsonConfig($settings, 'footer', 'global.footer');
            $this->applyJsonConfig($settings, 'cache', 'global.cache');
            $this->applyServerSettings($settings);
            $this->applyBattlePassSettings($settings);
            $this->applyInGameRankingSettings($settings);
        } catch (\Throwable) {
            // Database not ready (e.g. during migrations) — silently skip.
        }
    }

    /*
    |--------------------------------------------------------------------------
    | General / Scalar Settings
    |--------------------------------------------------------------------------
    */

    private function applyGeneralSettings(array $settings): void
    {
        // Start with config defaults
        $general = config('global', []);

        // Merge ALL scalar DB settings on top (not just keys from config)
        // This catches dynamic keys like item_stats_jid_2, job_name_jid_2, verify_jid_2
        foreach ($settings as $key => $value) {
            // Skip known JSON blob keys — they're handled by applyJsonConfig
            $jsonKeys = ['donate', 'widgets', 'ranking', 'history', 'referral', 'tickets', 'sliders', 'footer', 'mail', 'captcha', 'vote', 'cache', 'whatsapp', 'server', 'battlepass', 'ingame_ranking'];
            if (in_array($key, $jsonKeys, true)) {
                continue;
            }

            // Store every scalar setting
            $general[$key] = $value;
        }

        Config::set('global', $general);
        Config::set('app.name', $general['site_name'] ?? config('app.name'));
        Config::set('app.description', $general['site_desc'] ?? config('app.description'));
        Config::set('app.url', $general['site_url'] ?? config('app.url'));

        if (! empty($general['timezone'])) {
            date_default_timezone_set($general['timezone']);
        }

        if (! empty($general['theme'])) {
            $themePath = resource_path('themes/'.$general['theme'].'/views');
            if (is_dir($themePath)) {
                $this->app['view']->getFinder()->prependLocation($themePath);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Mail
    |--------------------------------------------------------------------------
    */

    private function applyMailSettings(array $settings): void
    {
        $mail = $this->decodeJson($settings['mail'] ?? null);

        if (empty($mail)) {
            return;
        }

        $nullable = static fn ($v) => ($v === '' || $v === 'null' || $v === null) ? null : $v;

        Config::set('mail.default', $mail['MAIL_MAILER'] ?? config('mail.default'));
        Config::set('mail.mailers.smtp.host', $mail['MAIL_HOST'] ?? config('mail.mailers.smtp.host'));
        Config::set('mail.mailers.smtp.port', $mail['MAIL_PORT'] ?? config('mail.mailers.smtp.port'));
        Config::set('mail.mailers.smtp.encryption', $nullable($mail['MAIL_SCHEME'] ?? config('mail.mailers.smtp.encryption')));
        Config::set('mail.mailers.smtp.username', $nullable($mail['MAIL_USERNAME'] ?? config('mail.mailers.smtp.username')));
        Config::set('mail.mailers.smtp.password', $nullable($mail['MAIL_PASSWORD'] ?? config('mail.mailers.smtp.password')));
        Config::set('mail.from.address', $mail['MAIL_FROM_ADDRESS'] ?? config('mail.from.address'));
        Config::set('mail.from.name', $mail['MAIL_FROM_NAME'] ?? config('mail.from.name'));
    }

    /*
    |--------------------------------------------------------------------------
    | Captcha
    |--------------------------------------------------------------------------
    */

    private function applyCaptchaSettings(array $settings): void
    {
        $captcha = $this->decodeJson($settings['captcha'] ?? null);

        if (empty($captcha)) {
            return;
        }

        Config::set('captcha.enabled', $captcha['enabled'] ?? config('captcha.enabled'));
        Config::set('captcha.secret', $captcha['secret'] ?? config('captcha.secret'));
        Config::set('captcha.sitekey', $captcha['sitekey'] ?? config('captcha.sitekey'));
        Config::set('captcha.options.timeout', $captcha['options']['timeout'] ?? 30);
    }

    /*
    |--------------------------------------------------------------------------
    | WhatsApp
    |--------------------------------------------------------------------------
    */

    private function applyWhatsappSettings(array $settings): void
    {
        $whatsapp = $this->decodeJson($settings['whatsapp'] ?? null);

        if (empty($whatsapp)) {
            return;
        }

        Config::set('services.whatsapp.enabled', $whatsapp['enabled'] ?? config('services.whatsapp.enabled'));
        Config::set('services.whatsapp.account_sid', $whatsapp['account_sid'] ?? config('services.whatsapp.account_sid'));
        Config::set('services.whatsapp.auth_token', $whatsapp['auth_token'] ?? config('services.whatsapp.auth_token'));
        Config::set('services.whatsapp.from', $whatsapp['from'] ?? config('services.whatsapp.from'));
        Config::set('services.whatsapp.welcome_enabled', $whatsapp['welcome_enabled'] ?? config('services.whatsapp.welcome_enabled'));
        Config::set('services.whatsapp.welcome_message', $whatsapp['welcome_message'] ?? config('services.whatsapp.welcome_message'));
        Config::set('services.whatsapp.confirm_enabled', $whatsapp['confirm_enabled'] ?? config('services.whatsapp.confirm_enabled'));
        Config::set('services.whatsapp.confirm_message', $whatsapp['confirm_message'] ?? config('services.whatsapp.confirm_message'));
    }

    /*
    |--------------------------------------------------------------------------
    | Vote
    |--------------------------------------------------------------------------
    */

    private function applyVoteSettings(array $settings): void
    {
        $vote = $this->decodeJson($settings['vote'] ?? null);

        if (empty($vote)) {
            return;
        }

        $hasEnabledSite = false;

        foreach ($vote as $site) {
            if (is_array($site) && ! empty($site['enabled'])) {
                $hasEnabledSite = true;
                break;
            }
        }

        Config::set('vote', $vote);

        Config::set('global.vote.enabled', $hasEnabledSite);
    }

    /*
    |--------------------------------------------------------------------------
    | History
    |--------------------------------------------------------------------------
    */

    private function applyHistorySettings(array $settings): void
    {
        $history = $this->decodeJson($settings['history'] ?? null);
        $defaults = config('global.logs', []);

        $merged = ! empty($history) ? array_replace_recursive($defaults, $history) : $defaults;
        $hasEnabledFeature = false;

        foreach ($merged as $key => $value) {
            if ($key !== 'enabled' && ! empty($value)) {
                $hasEnabledFeature = true;
                break;
            }
        }

        Config::set('global.logs', $merged);
        Config::set('global.logs.enabled', $hasEnabledFeature);
    }

    /*
    |--------------------------------------------------------------------------
    | Ranking
    |--------------------------------------------------------------------------
    */

    private function applyRankingSettings(): void
    {
        $hasEnabledRanking = collect(config('ranking.menu', []))
            ->merge(config('ranking.custom', []))
            ->contains(fn ($item) => is_array($item) && ! empty($item['enabled']));

        Config::set('ranking.enabled', $hasEnabledRanking);
    }

    /*
    |--------------------------------------------------------------------------
    | Event Schedule (widgets)
    |--------------------------------------------------------------------------
    */

    /**
     * Once the event schedule has been saved from the admin panel, the DB value
     * is the single source of truth. The generic config merge above would
     * re-add built-in events that the admin removed, so for this sub-key we
     * disable that merge as soon as the schedule has been populated. While it
     * has never been saved (names and custom both empty) the built-in events
     * from the config file are kept.
     */
    private function applyEventScheduleOverrides(array $settings): void
    {
        $widgets = $this->decodeJson($settings['widgets'] ?? null);
        $saved = $widgets['event_schedule'] ?? null;

        if (! is_array($saved)) {
            return;
        }

        $names = $saved['names'] ?? [];
        $custom = $saved['custom'] ?? [];

        if (empty($names) && empty($custom)) {
            return;
        }

        Config::set('widgets.event_schedule', $saved);
    }

    /*
    |--------------------------------------------------------------------------
    | Battle Pass (special handling — empty tiers list must be respected)
    |-------------------------------------------------------------------------- */

    private function applyBattlePassSettings(array $settings): void
    {
        $decoded = $this->decodeJson($settings['battlepass'] ?? null);
        if (empty($decoded)) {
            return;
        }

        $defaults = config('ingame.battlepass', []);
        $merged = array_replace_recursive($defaults, $decoded);

        // Tiers is an explicit list — once saved (even empty), it overrides config defaults.
        if (array_key_exists('tiers', $decoded)) {
            $merged['tiers'] = $decoded['tiers'];
        }

        Config::set('ingame.battlepass', $merged);
    }

    /*
    |--------------------------------------------------------------------------
    | In-Game Ranking (special handling — custom query list must not merge)
    |-------------------------------------------------------------------------- */

    private function applyInGameRankingSettings(array $settings): void
    {
        $decoded = $this->decodeJson($settings['ingame_ranking'] ?? null);
        if (empty($decoded)) {
            return;
        }

        $defaults = config('ingame.ranking', []);
        $merged = array_replace_recursive($defaults, $decoded);

        // The custom list is explicit — once saved (even empty / partially edited)
        // it overrides config defaults so removed rankings stay removed.
        if (array_key_exists('custom', $decoded)) {
            $merged['custom'] = $decoded['custom'];
        }

        Config::set('ingame.ranking', $merged);
    }

    /*
    |--------------------------------------------------------------------------
    | Server
    |--------------------------------------------------------------------------
    */

    private function applyServerSettings(array $settings): void
    {
        $version = config('global.server.version');

        $server = $this->decodeJson($settings['server'] ?? null);

        if (! empty($server)) {
            unset($server['version']);

            Config::set('global.server', array_replace_recursive(config('global.server', []), $server));
        }

        Config::set('global.server.version', $version);
    }

    /*
    |--------------------------------------------------------------------------
    | Generic JSON → Config
    |--------------------------------------------------------------------------
    */

    private function applyJsonConfig(array $settings, string $settingKey, string $configKey): void
    {
        $decoded = $this->decodeJson($settings[$settingKey] ?? null);

        if (empty($decoded)) {
            return;
        }

        if ($settingKey === 'donate') {
            Config::set($configKey, $this->mergeDonateConfig(config($configKey, []), $decoded));

            return;
        }

        $defaults = config($configKey, []);
        if (is_array($defaults) && is_array($decoded)) {
            $merged = array_replace_recursive($defaults, $decoded);
        } else {
            $merged = $decoded;
        }

        Config::set($configKey, $merged);
    }

    private function mergeDonateConfig(array $defaults, array $saved): array
    {
        $merged = $defaults;

        foreach ($saved as $key => $gateway) {
            if (! is_array($gateway)) {
                $merged[$key] = $gateway;

                continue;
            }

            $base = isset($merged[$key]) && is_array($merged[$key]) ? $merged[$key] : [];
            $mergedGateway = array_replace_recursive($base, $gateway);

            if (array_key_exists('package', $gateway)) {
                $mergedGateway['package'] = is_array($gateway['package'])
                    ? array_values($gateway['package'])
                    : [];
            }

            $merged[$key] = $mergedGateway;
        }

        return $merged;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function decodeJson(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }
}
