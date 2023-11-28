@props(['cols' => 6, 'rows' => 1])
<section
    {{ $attributes->merge(['class' => "@container flex flex-col p-3 sm:p-6 bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-900/5 default:col-span-full default:lg:col-span-{$cols} default:row-span-{$rows}"]) }}
    x-data="{
        loading: false,
        init() {
            Livewire.on('period-changed', () => (this.loading = true))

            Livewire.hook('commit', ({ component, succeed }) => {
                if (component.id === $wire.__instance.id) {
                    succeed(() => this.loading = false)
                }
            })
        }
    }"
>
    {{ $slot }}
</section>
