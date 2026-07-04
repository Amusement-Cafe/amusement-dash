<?php

use Livewire\Volt\Component;
use App\Models\Card;
use Livewire\Attributes\On;

new class extends Component {
    public $revealedCards = [];
    public $cards;
    public $flippedCards = [];
    public $userOwned = [];

    public function mount($revealedCards)
    {
        $this->revealedCards = $revealedCards;
        $this->cards = Card::whereIn('cardID', $this->revealedCards)->get();

        if (auth()->check()) {
            $userCards = \App\Models\UserCard::where('userID', auth()->user()->userID)
                ->whereIn('cardID', $this->cards->pluck('cardID'))
                ->get();
            foreach ($userCards as $uc) {
                $this->userOwned[$uc->cardID] = $uc->amount ?? 1;
            }
        }
    }

    public function revealAll()
    {
        $delay = 0;
        foreach ($this->cards as $card) {
            if (!in_array($card->cardID, $this->flippedCards)) {
                $this->flippedCards[] = $card->cardID;
                $this->js("setTimeout(() => { window.dispatchEvent(new CustomEvent('flip-card-ui', { detail: { cardId: " . $card->cardID . " } })); }, " . $delay . ");");
                $delay += 200;
            }
        }
    }

    public function flipCard($cardId)
    {
        if (!in_array($cardId, $this->flippedCards)) {
            $this->flippedCards[] = $cardId;
        }
    }

    public function closeReveal()
    {
        $this->dispatch('close-reveal');
    }
}; ?>

<div>
    <style>
        .card-reveal-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(10, 10, 15, 0.95);
            backdrop-filter: blur(10px);
            z-index: 10001;
            opacity: 0;
            animation: fadeInReveal 0.5s forwards ease-out;
            overflow-y: auto;
            display: flex;
            scrollbar-width: none;
        }
        .card-reveal-container::-webkit-scrollbar {
            display: none;
        }
        
        .card-reveal-content-wrapper {
            margin: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            padding: 4rem 0;
        }
        
        @keyframes fadeInReveal {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .card-reveal-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 3.5rem;
            width: 100%;
            max-width: 1400px;
            margin-bottom: 3rem;
            padding: 2rem;
            /* Removed overflow-y: auto so the grid never clips its children! */
        }
        
        .card-reveal-grid::-webkit-scrollbar {
            display: none;
        }

        .card-container {
            perspective: 1500px;
            width: 220px;
            height: 310px;
            animation: popIn 0.5s backwards cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
        }
        
        @keyframes popIn {
            from { transform: scale(0.5) translateY(50px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }

        .card-tilt-wrapper {
            width: 100%;
            height: 100%;
            transform: rotateX(var(--tilt-x, 0deg)) rotateY(var(--tilt-y, 0deg));
            transition: transform 0.1s ease-out;
            transform-style: preserve-3d;
            position: relative;
        }

        .card-flip-inner {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.3s ease-out, box-shadow 0.3s ease-out;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border-radius: 16px;
        }
        
        .card-container:hover .card-flip-inner {
            box-shadow: 0 15px 40px rgba(168, 85, 247, 0.2);
        }

        .card-flip-inner.flipped {
            transform: rotateY(180deg) scale(1.15) translateY(0);
            animation: flipPop 0.8s linear;
            z-index: 10;
        }
        
        @keyframes flipPop {
            0% { 
                transform: rotateY(0deg) scale(1) translateY(0); 
                box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                animation-timing-function: ease-in;
            }
            40% { 
                transform: rotateY(90deg) scale(1.6) translateY(-40px); 
                box-shadow: 0 50px 100px rgba(0,0,0,0.7);
                /* Extremely strong deceleration for the second half */
                animation-timing-function: cubic-bezier(0.1, 1, 0.2, 1);
            }
            100% { 
                transform: rotateY(180deg) scale(1.18) translateY(-5px); 
                box-shadow: 0 15px 40px rgba(168, 85, 247, 0.2);
            }
        }
        
        .card-container:hover .card-flip-inner.flipped {
            transform: rotateY(180deg) scale(1.18) translateY(-5px);
        }
        
        /* Image Ambient Glow - Expanded bounding box to prevent 3D blur clipping */
        .ambient-glow {
            position: absolute;
            top: -40px; left: -40px; right: -40px; bottom: -40px; 
            background-image: var(--bg-img);
            background-size: 230px 320px; 
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(20px) saturate(1.8);
            opacity: 0;
            transform: rotateY(180deg) translateZ(-20px);
            transition: opacity 0.5s ease-in, filter 0.5s ease-out;
            pointer-events: none;
        }
        
        .card-flip-inner.flipped .ambient-glow {
            opacity: 0.8;
        }
        
        .card-container:hover .card-flip-inner.flipped .ambient-glow {
            opacity: 1;
            filter: blur(30px) saturate(2.2);
        }

        .card-face {
            position: absolute;
            width: 100%;
            height: 100%;
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            border-radius: 16px;
            /* Removed overflow: hidden; to prevent breaking backface-visibility in Firefox */
        }

        .card-back {
            transform: rotateY(0deg);
            border: 2px solid #a855f7;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: inset 0 0 50px rgba(168, 85, 247, 0.2);
            background-color: #0f0f1a;
            background-image: 
                radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(168,85,247,0.2) 0%, transparent 70%),
                linear-gradient(45deg, rgba(255,255,255,0.03) 25%, transparent 25%, transparent 75%, rgba(255,255,255,0.03) 75%, rgba(255,255,255,0.03)), 
                linear-gradient(45deg, rgba(255,255,255,0.03) 25%, transparent 25%, transparent 75%, rgba(255,255,255,0.03) 75%, rgba(255,255,255,0.03));
            background-size: 100% 100%, 30px 30px, 30px 30px;
            background-position: 0 0, 0 0, 15px 15px;
        }
        
        .logo-ring {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 2px solid rgba(168,85,247,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 0 20px rgba(168,85,247,0.4), inset 0 0 20px rgba(168,85,247,0.4);
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
        }
        
        /* Dynamic Glare on Card Back */
        .card-back::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            border-radius: inherit;
            background: radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(255,255,255,0.3) 0%, transparent 60%);
            pointer-events: none;
            mix-blend-mode: overlay;
        }
        
        .card-front {
            transform: rotateY(180deg);
            background: transparent;
        }
        
        /* Dynamic Glare and Foil on Card Front */
        .card-front::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            border-radius: inherit;
            background: radial-gradient(circle at calc(100% - var(--mouse-x, 50%)) var(--mouse-y, 50%), rgba(255,255,255,0.4) 0%, transparent 50%),
                        linear-gradient(125deg, transparent 20%, rgba(255,255,255,0.2) 40%, rgba(255,255,255,0.5) 50%, rgba(255,255,255,0.2) 60%, transparent 80%);
            background-position: center, calc((100% - var(--mouse-x, 50%) - 50%) * 1.5) center;
            background-size: 100% 100%, 200% 200%;
            background-repeat: no-repeat;
            pointer-events: none;
            mix-blend-mode: overlay;
            transition: background-position 0.1s ease-out;
        }
        
        /* Multi-color shine wrapper */
        .multi-color-shine-wrapper {
            position: absolute;
            top: 50%; left: 50%;
            width: 140%; height: 140%;
            transform: translate(-50%, -50%) scale(0.8);
            opacity: 0;
            z-index: -1;
            pointer-events: none;
            transition: opacity 1.5s ease-out, transform 1.5s ease-out;
        }

        .multi-color-shine-wrapper.active {
            opacity: 0.8;
            transform: translate(-50%, -50%) scale(1.15);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
        }

        .multi-color-shine-spinner {
            width: 100%; height: 100%;
            background: conic-gradient(from 0deg, rgba(6, 182, 212, 0.4), rgba(168, 85, 247, 0.4), rgba(59, 130, 246, 0.4), rgba(217, 70, 239, 0.4), rgba(6, 182, 212, 0.4));
            border-radius: 50%;
            filter: blur(40px);
            animation: spinShine 6s linear infinite;
        }

        @keyframes spinShine {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Blinding Light Flash on Flip */
        .flash-bang {
            position: absolute;
            top: -100px; left: -100px; right: -100px; bottom: -100px;
            background: radial-gradient(circle at center, rgba(255,255,255,1) 0%, rgba(255,255,255,0.8) 20%, transparent 60%);
            opacity: 0;
            pointer-events: none;
            z-index: 100;
            /* Render it on the front side of the card */
            transform: rotateY(180deg) translateZ(10px);
        }
        
        .card-flip-inner.flipped .flash-bang {
            animation: flashBurst 0.8s linear forwards;
        }
        
        @keyframes flashBurst {
            0%, 35% { opacity: 0; transform: rotateY(180deg) translateZ(10px) scale(0.5); }
            40% { opacity: 1; transform: rotateY(180deg) translateZ(10px) scale(1.5); }
            100% { opacity: 0; transform: rotateY(180deg) translateZ(10px) scale(1); }
        }
        
        .card-reveal-actions {
            display: flex;
            gap: 1.5rem;
            animation: fadeInReveal 0.5s 1s backwards ease-out;
        }
    </style>

    <div class="card-reveal-container">
        <div class="card-reveal-content-wrapper">
            <h2 style="color: white; font-size: 2.5rem; margin-bottom: 2rem; text-shadow: 0 0 20px rgba(255,255,255,0.5); animation: popIn 0.5s backwards ease-out;">
                Cards Acquired!
            </h2>
            
            <div class="card-reveal-grid">
            @foreach($cards as $index => $card)
                <div class="card-container" 
                     style="animation-delay: {{ $index * 0.1 }}s;" 
                     id="card-{{ $card->cardID }}" 
                     x-data="{ 
                        tiltX: 0, 
                        tiltY: 0, 
                        mouseX: 50, 
                        mouseY: 50, 
                        flipped: {{ in_array($card->cardID, $flippedCards) ? 'true' : 'false' }},
                        justFlipped: false
                     }"
                     @mousemove="
                        let rect = $el.getBoundingClientRect();
                        let x = $event.clientX - rect.left;
                        let y = $event.clientY - rect.top;
                        mouseX = (x / rect.width) * 100;
                        mouseY = (y / rect.height) * 100;
                        tiltX = ((y / rect.height) - 0.5) * -30;
                        tiltY = ((x / rect.width) - 0.5) * 30;
                     "
                     @mouseleave="
                        tiltX = 0; tiltY = 0; mouseX = 50; mouseY = 50;
                     "
                     @click="
                        if (!flipped) {
                            flipped = true;
                            justFlipped = true;
                            setTimeout(() => justFlipped = false, 3000);
                            $wire.flipCard({{ $card->cardID }});
                        }
                     "
                     @flip-card-ui.window="
                        if ($event.detail.cardId == {{ $card->cardID }} && !flipped) {
                            flipped = true;
                            justFlipped = true;
                            setTimeout(() => justFlipped = false, 3000);
                        }
                     ">
                     
                    <!-- Multi-color shine effect behind the card -->
                    <div class="multi-color-shine-wrapper" :class="justFlipped ? 'active' : ''">
                        <div class="multi-color-shine-spinner"></div>
                    </div>
                     
                    <div class="card-tilt-wrapper" :style="`--tilt-x: ${tiltX}deg; --tilt-y: ${tiltY}deg; --mouse-x: ${mouseX}%; --mouse-y: ${mouseY}%;`">
                        <div class="card-flip-inner" :class="flipped ? 'flipped' : ''">
                            <!-- Blinding Light Burst -->
                            <div class="flash-bang"></div>
                            
                            <!-- Ambient glow perfectly matches card colors -->
                            <div class="ambient-glow" style="--bg-img: url('{{ $card->cardURL }}');"></div>
                            
                            <div class="card-face card-back">
                                <div class="logo-ring">
                                    <img src="https://amu.cards/favicon.ico" alt="Amusement Club" style="width: 60px; height: 60px; filter: drop-shadow(0 0 10px rgba(255,255,255,0.6)); opacity: 0.95;">
                                </div>
                            </div>
                            <div class="card-face card-front">
                                <img src="{{ $card->cardURL }}" alt="{{ $card->displayName }}" style="width: 100%; height: 100%; object-fit: contain; border-radius: 16px; filter: brightness(0.9) saturate(1.15);" />
                                
                                @php $ownedCount = $userOwned[$card->cardID] ?? 0; @endphp
                                @if($ownedCount > 0)
                                    <div style="position: absolute; top: 0; right: 0; background: #34d399; color: black; font-size: 0.8rem; font-weight: bold; padding: 4px 10px; border-bottom-left-radius: 12px; z-index: 10; display: flex; flex-direction: column; align-items: center; border-top-right-radius: 14px;">
                                        <span>OWNED</span>
                                        @if(is_numeric($ownedCount) && $ownedCount > 1)
                                            <span style="font-size: 0.75rem; background: rgba(0,0,0,0.15); border-radius: 4px; padding: 1px 6px; margin-top: 2px;">{{ $ownedCount }}x</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
            <div class="card-reveal-actions">
                @if(count($flippedCards) < count($cards))
                    <button wire:click="revealAll" class="btn" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.3); font-size: 1.2rem; padding: 1rem 2rem; backdrop-filter: blur(5px);">
                        <i class="ph-bold ph-magic-wand"></i> Reveal All
                    </button>
                @else
                    <button wire:click="closeReveal" class="btn btn-primary" style="font-size: 1.2rem; padding: 1rem 2.5rem; box-shadow: 0 0 20px rgba(99, 102, 241, 0.5);">
                        <i class="ph-bold ph-check"></i> Continue
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
