@use('Laravel\Pulse\Facades\Pulse')
@props(['cols' => 12, 'fullWidth' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>Laravel Pulse</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600" rel="stylesheet" />
        <link href="data:image/x-icon;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAMAAABEpIrGAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAACQFBMVEUAAAD6WlXyWmb2Wl77WlXxWmPqWmzpWmzdWnvhWnfjWnXUWojaWoDTWonMWpHLWpLAWqDCWp7CWp3DWp23Wqu7Wqe6WqisWrm2Wq2xWrPsWWalWsKsWrqkWsSGWumDWu2BWu97Wvd7WvZ6Wvh8WvZ5Wvn4Wlr4WlrxWmPyWmHyWmHwWmTpWm3qWmzqWmzpWm3gWnjhWnjgWnjRWovXWoTXWoTMWpHNWpDNWpDLWpPAWqDBWp/AWqDDWpzEWpvEWpvEWpvDWp23Wqy6Wqi6Wqi6Wqi7Wqe7Wqe6Wqe6WqiwWrWxWrOxWrSrWruxWrSyWrKxWrOwWrSlWsOnWsCnWr+mWsGnWr+oWr6oWr+nWsCaWtCaWtCaWs+aWtCdWs2fWsqeWsqeWsudWsyeWsueWsufWsmfWsqeWsucWs6aWtCaWs+aWtCZWtGUWteVWteXWtSVWtaUWteUWteVWteXWtSVWtaVWteUWteSWtqUWtiUWteUWtePWt6OWt+OWt+OWt+OWt+XWtSMWuKLWuOLWuKMWuGLWuOLWuOKWuSLWuOLWuKNWuCOWt+QWt2DWu2CWu+CWu6EWuuCWu6BWu+BWu+DWu17WvZ7Wvd7Wvd7Wvd7Wvd7Wvd8WvZ6Wvh6Wvh6Wvh6Wvh6Wvh6Wvh6Wvh6Wvh6Wvh6Wvh6Wvh6Wvh6Wvh6Wvh6Wvh6Wvh6Wvh6Wvh6WvjhWnfXWoPNWo/EWpu7Wqe6WqexWrOnWr+oWr+eWsuUWteLWuOBWu97Wvd6Wvj///+vWn4hAAAAsHRSTlMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA4NFbGpEEL28DVy/l0CpYoU0bUHM3EaNe/+2RoTwHNi0+PzOWz8sQWVj7VhI9beLsJTiI4xZWhmpsvY+Xjk6iZcuQcnaVIMt/JNk9j8xgs289wtuO9ESIeKi2ADWvyXGdjzn/jgjnEVKeZkBrP6/XEKvPA2iMgXgtIVW/74XDrVhwImyqMOGAsBFqm/VNAAAAABYktHRL/T27NVAAAAB3RJTUUH5wsQDgkSyLkAHwAAASpJREFUOMtjYBh2gJFJTZ2ZBY8CVg1NLW1WPArYdHT19Nlxy3NwGmwwNOLkwqmA29hk40ZTHl6cCvjMzDdtsrDkxyYnIGhlbSNka2e/2cFRGJsCESfnLS6irlvd3D08xbDIi0t4eW/z8fXb5h+wLVBSClOBdFDw9u0hoWE7wiO2R8rIoshFRcfExMTG7YxPSExKTklN25mekQkSAoOs7ByG3F1gkJdfsKuwqLiktKy8onIXHFRVM9TU1gFBfUNjU/Pu3S1yrW3tHZ1d3XUQUN/TC7dKvq9/z54JChMn7Zk8ZaqiHKY7laZN3ztjpqLyrL17Z89RweJTVeW5++bNV1ZesHDRYmVVbGGlvGTpsuXKyitWrlqtjDUmlJevWausrLxu/Wpl7ApGNgAAUsxoMcT6INsAAAAldEVYdGRhdGU6Y3JlYXRlADIwMjMtMTEtMTZUMTQ6MDk6MTgrMDA6MDANsDzYAAAAJXRFWHRkYXRlOm1vZGlmeQAyMDIzLTExLTE2VDE0OjA5OjE4KzAwOjAwfO2EZAAAAFd6VFh0UmF3IHByb2ZpbGUgdHlwZSBpcHRjAAB4nOPyDAhxVigoyk/LzEnlUgADIwsuYwsTIxNLkxQDEyBEgDTDZAMjs1Qgy9jUyMTMxBzEB8uASKBKLgDqFxF08kI1lQAAAABJRU5ErkJggg==" rel="icon" type="image/x-icon">

        {!! Pulse::css() !!}
        @livewireStyles

        {!! Pulse::js() !!}
        @livewireScriptConfig
    </head>
    <body class="bg-gray-50 dark:bg-gray-950 font-sans antialiased">
        <div class="min-h-screen">
            <header class="px-5">
                <div class="{{ $fullWidth ? '' : 'container' }} py-3 sm:py-5 mx-auto border-b border-gray-200 dark:border-gray-900">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg width="33" height="32" viewBox="0 0 33 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M15.4566 6.75005C15.9683 6.75596 16.4047 7.09621 16.5001 7.56364L18.8747 19.2038L19.999 17.1682C20.1832 16.8347 20.5526 16.625 20.9559 16.625H31.1744C31.7684 16.625 32.25 17.0727 32.25 17.625C32.25 18.1773 31.7684 18.625 31.1744 18.625H21.6127L19.3581 22.7068C19.1483 23.0867 18.7021 23.3008 18.2475 23.2397C17.7928 23.1786 17.4301 22.8559 17.3445 22.4363L15.376 12.7868L13.1334 22.4607C13.0282 22.9146 12.6007 23.2414 12.1013 23.2498C11.6019 23.2582 11.162 22.9458 11.0393 22.4957L9.30552 16.1378L8.19223 18.0929C8.00581 18.4202 7.64002 18.625 7.2416 18.625H1.32563C0.731576 18.625 0.25 18.1773 0.25 17.625C0.25 17.0727 0.731576 16.625 1.32563 16.625H6.59398L8.71114 12.9071C8.9193 12.5415 9.34805 12.3328 9.78979 12.3821C10.2315 12.4313 10.5951 12.7283 10.7044 13.1292L11.9971 17.8695L14.3918 7.53929C14.4996 7.07421 14.9449 6.74414 15.4566 6.75005Z" fill="url(#paint0_linear_4_31)"/>
                                <defs>
                                    <linearGradient id="paint0_linear_4_31" x1="16.25" y1="6.74997" x2="16.25" y2="23.25" gradientUnits="userSpaceOnUse">
                                        <stop stop-color="#F85A5A"/>
                                        <stop offset="0.828125" stop-color="#7A5AF8"/>
                                    </linearGradient>
                                </defs>
                            </svg>
                            <span class="ml-2 text-lg sm:text-2xl text-gray-700 dark:text-gray-300 font-medium"><b class="font-bold">Laravel</b> Pulse</span>
                        </div>
                        <div class="flex items-center gap-3 sm:gap-6">
                            <livewire:pulse.period-selector />
                            <x-pulse::theme-switcher />
                        </div>
                    </div>
                </div>
            </header>

            <main class="pt-6 px-6 pb-12">
                <div {{ $attributes->merge(['class' => "mx-auto grid default:grid-cols-{$cols} default:gap-6" . ($fullWidth ? '' : ' container')]) }}>
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
