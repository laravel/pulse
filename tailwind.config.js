const defaultTheme = require('tailwindcss/defaultTheme')

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./resources/views/**/*.blade.php"],
    theme: {
        extend: {
            fontFamily: {
                'sans': ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [require("@tailwindcss/forms")],
};
