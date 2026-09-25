import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                serif: ['"Newsreader"', 'Georgia', 'serif'],
                newsreader: ['"Newsreader"', 'Georgia', 'serif'],
                devanagari: ['"Noto Sans Devanagari"', ...defaultTheme.fontFamily.sans],
                display: ['"Newsreader"', 'Georgia', 'serif'],
                heading: ['"Newsreader"', 'Georgia', 'serif'],
                mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', 'monospace'],
            },
            colors: {
                brand: {
                    primary: '#1a1a1a',
                    'primary-hover': '#000000',
                    brass: '#a47a3c',
                    'brass-text': '#7a5620',
                    'brass-tint': '#f5eee2',
                    bg: '#fbf9f5',
                    card: '#f3efe7',
                    fill: '#ece7de',
                    border: '#e0d9cc',
                    main: '#1d1d1f',
                    secondary: '#6e6e73',
                    hint: '#86868b',
                }
            }
        },
    },

    plugins: [forms],
};
