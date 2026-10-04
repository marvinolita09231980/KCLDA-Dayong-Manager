<div x-data="{ copied: false, copyError: false }">
    @if($totalCents > 0)
        <p class="mb-4 text-sm text-gray-600">Review the reminder for {{ $memberName }}. It includes outstanding amounts for the selected cycles, after all recorded payments.</p>
        <label for="obligation-message" class="mb-2 block font-medium">Message</label>
        <textarea id="obligation-message" x-ref="message" rows="14" readonly class="w-full rounded-lg border border-gray-300 p-3 text-sm">{{ $message }}</textarea>
        <p class="my-4 text-sm text-gray-600">Copy the message, open Messenger, choose the member's chat, paste and send. Sending directly from this app requires a connected Facebook Page.</p>
        <div class="flex flex-wrap gap-3">
            <x-filament::button type="button" x-on:click="
                copyError = false;
                try {
                    await navigator.clipboard.writeText($refs.message.value);
                    copied = true;
                } catch (error) {
                    $refs.message.focus();
                    $refs.message.select();
                    copied = false;
                    copyError = true;
                }
            ">Copy message</x-filament::button>
            <x-filament::button tag="a" href="https://www.messenger.com/" target="_blank" rel="noopener noreferrer" color="gray">Open Messenger</x-filament::button>
        </div>
        <p x-show="copied" x-cloak role="status" class="mt-3 text-sm">Message copied. Paste it into {{ $memberName }}'s chat and send it in Messenger.</p>
        <p x-show="copyError" x-cloak role="alert" class="mt-3 text-sm">Automatic copying is unavailable. The message is selected; copy it manually, then paste it in Messenger.</p>
    @else
        <p>No outstanding balance remains for the selected cycles after all recorded payments. No reminder is needed.</p>
    @endif
</div>
