@props([
    'block',
    'ownerRecord',
    'pageClass',
    'wireKey',
])

<div class="crm-payment-block">
    <div class="crm-payment-block-title">{{ $block['title'] }}</div>
    @livewire($block['relation_manager'], [
        'ownerRecord' => $ownerRecord,
        'pageClass' => $pageClass,
    ], key($wireKey))
</div>
