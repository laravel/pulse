const defaultTheme = require('tailwindcss/defaultTheme')

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./resources/views/**/*.blade.php"],
    safelist: [
        {
            pattern: /col-span-(\d+|full)/,
        },
        {
            pattern: /grid-cols-(\d+)/,
        }
    ],
    theme: {
        extend: {
            fontFamily: {
                'sans': ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [require("@tailwindcss/forms")],
};
