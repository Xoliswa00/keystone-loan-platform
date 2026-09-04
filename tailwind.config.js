import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            colors: {
                'kc-navy':        '#071B34',
                'kc-navy-light':  '#0D2B4E',
                'kc-navy-mid':    '#0A2240',
                'kc-gold':        '#C89B3C',
                'kc-gold-light':  '#D4AD56',
                'kc-gold-muted':  '#A07D2E',
                'kc-white':       '#F5F5F2',
                'kc-charcoal':    '#1A1A1A',
                'kc-silver':      '#B8BDC7',
                'kc-silver-light':'#E8E9EC',
            },
            fontFamily: {
                sans:    ['Source Sans 3', ...defaultTheme.fontFamily.sans],
                display: ['Bespoke Sans', 'Source Sans 3', ...defaultTheme.fontFamily.sans],
                // Pinned, not left to the OS. Reference codes and rand figures
                // are a Keystone signature (.kc-figure) — without a named face
                // they rendered as Consolas on Windows, SF Mono on macOS and
                // Roboto Mono on Android, i.e. a different signature per device.
                mono:    ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
            animation: {
                fadeIn:    'fadeIn 0.5s ease-out forwards',
                slideIn:   'slideIn 0.4s ease-out forwards',
            },
            keyframes: {
                fadeIn: {
                    '0%':   { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                slideIn: {
                    '0%':   { opacity: '0', transform: 'translateX(-12px)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
            },
            boxShadow: {
                'kc-card':  '0 2px 12px rgba(7,27,52,0.08)',
                'kc-gold':  '0 4px 16px rgba(200,155,60,0.25)',
                'kc-navy':  '0 4px 24px rgba(7,27,52,0.30)',
            },
        },
    },

    plugins: [forms],
};
