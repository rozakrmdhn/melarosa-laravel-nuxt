<?php

namespace App\Providers;

use App\Helpers\Image;
use App\Helpers\Utils;
use App\Models\BatasWilayahDesa;
use App\Models\BatasWilayahKecamatan;
use App\Models\InfrastrukturSegmen;
use App\Models\JalanPorosDesa;
use App\Models\MonitoringRealisasi;
use App\Models\PlottingAnggaran;
use App\Models\User;
use App\Policies\BatasWilayahDesaPolicy;
use App\Policies\BatasWilayahKecamatanPolicy;
use App\Policies\InfrastrukturSegmenPolicy;
use App\Policies\JalanPorosDesaPolicy;
use App\Policies\MonitoringRealisasiPolicy;
use App\Policies\PlottingAnggaranPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ...
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(BatasWilayahKecamatan::class, BatasWilayahKecamatanPolicy::class);
        Gate::policy(BatasWilayahDesa::class, BatasWilayahDesaPolicy::class);
        Gate::policy(JalanPorosDesa::class, JalanPorosDesaPolicy::class);
        Gate::policy(InfrastrukturSegmen::class, InfrastrukturSegmenPolicy::class);
        Gate::policy(PlottingAnggaran::class, PlottingAnggaranPolicy::class);
        Gate::policy(MonitoringRealisasi::class, MonitoringRealisasiPolicy::class);

        // Super-admin role bypass for all permissions
        Gate::before(static function ($user, $ability) {
            return $user->hasRole('admin') ? true : null;
        });

        RateLimiter::for('api', static function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('verification-notification', static function (Request $request) {
            return Limit::perMinute(1)->by($request->user()?->email ?: $request->ip());
        });

        RateLimiter::for('uploads', static function (Request $request) {
            return $request->user()?->hasRole('admin')
                ? Limit::none()
                : Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('login', static function (Request $request) {
            return Limit::perMinute(5)
                ->by(Str::transliterate(implode('|', [
                    strtolower($request->input('email')),
                    $request->ip()
                ])))
                ->response(static function (Request $request, array $headers): void {
                    event(new Lockout($request));

                    throw ValidationException::withMessages([
                        'email' => trans('auth.throttle', [
                            'seconds' => $headers['Retry-After'],
                            'minutes' => ceil($headers['Retry-After'] / 60),
                        ]),
                    ]);
                });
        });

        ResetPassword::createUrlUsing(static function (object $notifiable, string $token) {
            return config('app.frontend_url') . '/auth/reset/' . $token . '?email=' . $notifiable->getEmailForPasswordReset();
        });

        VerifyEmail::createUrlUsing(static function (object $notifiable) {
            $url = url()->temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'uuid' => $notifiable->uuid,
                    'hash' => hash('sha256', $notifiable->getEmailForVerification()),
                ]
            );

            return config('app.frontend_url') . '/auth/verify?verify_url=' . urlencode($url);
        });

        /**
         * Convert uploaded image to webp, jpeg or png format and resize it
         */
        UploadedFile::macro('convert', function (?int $width = null, ?int $height = null, string $extension = 'webp', int $quality = 90) {
            return tap($this, static function (UploadedFile $file) use ($width, $height, $extension, $quality) {
                Image::convert($file->path(), $file->path(), $width, $height, $extension, $quality);
            });
        });

        /**
         * Remove all special characters from a string
         */
        Str::macro('onlyWords', static function (string $text): string {
            // \p{L} matches any kind of letter from any language
            // \d matches a digit in any script
            return Str::replaceMatches('/[^\p{L}\d ]/u', '', $text);
        });

        Request::macro('device', function () {
            return Utils::getDeviceDetectorByUserAgent($this->userAgent());
        });

        Request::macro('deviceName', function (): string {
            return Utils::getDeviceNameFromDetector($this->device());
        });
    }
}
