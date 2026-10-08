<div>
    @if ($sent)
        <output class="notice notice-ok" tabindex="-1" x-data x-init="$el.focus()">
            <span class="notice-title">Thank you, we have your enquiry.</span>
            <span>@if ($sent !== 'ENQ') Your reference is <strong>{{ $sent }}</strong>. @endif We will get back to you using the details you gave.</span>
        </output>
    @else
        <form wire:submit="submit" class="form" novalidate>
            <div class="field">
                <label for="enq-kind">What is this about?</label>
                <select id="enq-kind" wire:model="kind" aria-describedby="@error('kind') enq-kind-err @enderror">
                    @foreach ($kinds as $k)
                        <option value="{{ $k->value }}">{{ $k->label() }}</option>
                    @endforeach
                </select>
                @error('kind') <p class="error" id="enq-kind-err">{{ $message }}</p> @enderror
            </div>

            <div class="grid-2">
                <div class="field">
                    <label for="enq-name">Your name <span class="req">(required)</span></label>
                    <input id="enq-name" type="text" wire:model="name" autocomplete="name" maxlength="150" required @error('name') aria-invalid="true" aria-describedby="enq-name-err" @enderror>
                    @error('name') <p class="error" id="enq-name-err">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="enq-org">Farm or business</label>
                    <input id="enq-org" type="text" wire:model="organisation" autocomplete="organization" maxlength="150">
                </div>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label for="enq-email">E-mail</label>
                    <input id="enq-email" type="email" wire:model="email" autocomplete="email" maxlength="255" @error('email') aria-invalid="true" aria-describedby="enq-email-err" @enderror>
                    @error('email') <p class="error" id="enq-email-err">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="enq-phone">Phone or WhatsApp</label>
                    <input id="enq-phone" type="tel" wire:model="phone" autocomplete="tel" maxlength="40" @error('phone') aria-invalid="true" aria-describedby="enq-phone-err" @enderror>
                    @error('phone') <p class="error" id="enq-phone-err">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="hint">We need at least one of e-mail or phone to reply.</p>

            <div class="field">
                <label for="enq-qty">How many or how much?</label>
                <input id="enq-qty" type="text" wire:model="quantity" maxlength="100" placeholder="For example: 20 weaners, 50 doses, 40 kg of loin">
            </div>

            <div class="field">
                <label for="enq-msg">Your message <span class="req">(required)</span></label>
                <textarea id="enq-msg" rows="5" wire:model="message" maxlength="2000" required @error('message') aria-invalid="true" aria-describedby="enq-msg-err" @enderror></textarea>
                @error('message') <p class="error" id="enq-msg-err" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="trap" aria-hidden="true">
                <label for="enq-website">Leave this field empty</label>
                <input id="enq-website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove>Send enquiry</span><span wire:loading>Sending...</span>
            </button>
            <p class="hint">An enquiry is a request, not an order. We reply with availability and a quote.</p>
        </form>
    @endif
</div>
