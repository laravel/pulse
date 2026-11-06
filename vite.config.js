/** @type {import('vite').UserConfig} */
export default {
    build: {
        assetsDir: "",
        rollupOptions: {
            // Distinct entry names, otherwise Vite 6 gives the CSS entry "pulse.js" and renames the script to "pulse2.js".
            input: {
                pulse: "resources/js/pulse.js",
                styles: "resources/css/pulse.css",
            },
            output: {
                assetFileNames: "pulse[extname]",
                entryFileNames: "[name].js",
            },
        },
    },
};
