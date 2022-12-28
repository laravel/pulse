/** @type {import('vite').UserConfig} */
export default {
    build: {
        assetsDir: "",
        rollupOptions: {
            input: ["resources/js/pulse.js", "resources/css/pulse.css"],
            output: {
                assetFileNames: "[name][extname]",
                entryFileNames: "[name].js",
            },
        },
    },
};
