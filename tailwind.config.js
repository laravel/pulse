const defaultTheme = require('tailwindcss/defaultTheme')

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./resources/views/**/*.blade.php"],
    safelist: [
        {
            pattern: /grid-cols-(\d+)/,
            variants: ['sm', 'md', 'lg', 'xl', '2xl', 'default', 'default:lg'],
        },
        {
            pattern: /(row|col)-span-(\d+|full)/,
            variants: ['sm', 'md', 'lg', 'xl', '2xl', 'default', 'default:lg'],
        },
    ],
    darkMode: 'class',
    theme: {
        extend: {
            fontFamily: {
                'sans': ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [
        require("@tailwindcss/forms"),
        require("@tailwindcss/container-queries"),
        function ({ addVariant }) {
            addVariant('default', 'html :where(&)')
        }
    ],
};
