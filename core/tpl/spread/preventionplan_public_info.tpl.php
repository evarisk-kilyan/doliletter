<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/tpl/spread/preventionplan_public_info.tpl.php
 * \ingroup doliletter
 * \brief   Read-only public display of a prevention plan: one block per risk, holding its picto,
 *          its description, the protections that apply to it and a carousel of the photos taken on site.
 *          Certification photos are uploaded per signatory (Saturne media block) in the signatories list.
 *          Each block ends with an acknowledgement the visitor must give before being able to sign.
 *          Expects: $langs, $ppRisks, $ppOrphanProtections, $signSignatory, $ppAcknowledgedRisks.
 */
?>
<style>
.pp-public-block { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 16px; margin: 16px 0; }
.pp-public-block__title { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; color: #333; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #eee; }
.pp-public-block__title i { color: #3b82f6; }
.pp-public-grid { display: flex; flex-wrap: wrap; gap: 12px; }
.pp-public-item { display: flex; align-items: center; gap: 10px; min-width: 220px; padding: 8px 10px; border: 1px solid #e5e5e5; border-radius: 8px; }
.pp-public-item img { width: 44px; height: 44px; object-fit: contain; flex: 0 0 auto; }
.pp-public-item__name { font-size: 13px; font-weight: 600; color: #333; }
.pp-public-item__comment { font-size: 12px; color: #666; }
.pp-public-badge { display: inline-block; margin-top: 4px; padding: 2px 8px; font-size: 11px; font-weight: 600; color: #fff; background: #ef4444; border-radius: 10px; }
.pp-risk-block { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 14px; margin: 12px 0; }
.pp-risk-block__header { display: flex; align-items: center; gap: 12px; }
.pp-risk-block__picto { width: 56px; height: 56px; object-fit: contain; flex: 0 0 auto; }
.pp-risk-block__name { font-size: 15px; font-weight: 600; color: #333; }
/* Reste en haut a droite du bloc, quelle que soit la hauteur du nom et de la description */
.pp-risk-block__step { flex: 0 0 auto; align-self: flex-start; margin-left: auto; padding: 3px 10px; font-size: 12px; font-weight: 600; white-space: nowrap; color: #1d4ed8; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; }
.pp-risk-block__comment { margin-top: 4px; font-size: 13px; color: #666; white-space: pre-line; }
.pp-risk-block__section { margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee; }
.pp-risk-block__label { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #666; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.pp-risk-block__label i { color: #3b82f6; }
.pp-risk-protections { display: flex; flex-wrap: wrap; gap: 10px; }
.pp-risk-protection { display: flex; align-items: center; justify-content: center; width: 56px; }
.pp-risk-protection img { width: 52px; height: 52px; object-fit: contain; }
/* Recapitulatif de fin de page : uniquement des pictogrammes, tous les risques a la suite puis
   tous les moyens de prevention. Les noms restent en infobulle et en texte alternatif. */
.pp-recap__line { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
.pp-recap__line--protections { margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee; }
.pp-recap__line img { object-fit: contain; flex: 0 0 auto; }
.pp-recap__line--risks img { width: 46px; height: 46px; }
.pp-recap__line--protections img { width: 44px; height: 44px; }
.pp-carousel { position: relative; }
.pp-carousel__track { display: flex; gap: 8px; overflow-x: auto; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
.pp-carousel__track::-webkit-scrollbar { display: none; }
.pp-carousel__slide { flex: 0 0 100%; scroll-snap-align: center; }
/* contain, pas cover : une photo de terrain doit se lire en entier, quitte a laisser des bandes */
.pp-carousel__slide img { display: block; width: 100%; height: 260px; object-fit: contain; border-radius: 6px; background: #f3f4f6; }
.pp-carousel__nav { position: absolute; top: 50%; transform: translateY(-50%); display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; padding: 0; font-size: 15px; color: #fff; background: rgba(17, 24, 39, 0.55); border: none; border-radius: 50%; cursor: pointer; transition: opacity .2s; }
.pp-carousel__nav:hover { background: rgba(17, 24, 39, 0.75); }
.pp-carousel__nav--prev { left: 8px; }
.pp-carousel__nav--next { right: 8px; }
.pp-carousel__nav--idle { opacity: 0.3; }
/* La fleche droite bat tant qu'il reste des photos a voir */
.pp-carousel__nav--pulse { animation: pp-carousel-pulse 2.4s ease-in-out infinite; }
@keyframes pp-carousel-pulse {
  0%, 100% { transform: translateY(-50%) scale(1); }
  50%      { transform: translateY(-50%) scale(1.07); }
}
@media (prefers-reduced-motion: reduce) { .pp-carousel__nav--pulse { animation: none; } }
.pp-carousel__counter { position: absolute; top: 8px; right: 8px; padding: 3px 9px; font-size: 12px; font-weight: 600; color: #fff; background: rgba(17, 24, 39, 0.65); border-radius: 12px; pointer-events: none; }
.pp-carousel--single .pp-carousel__counter { display: none; }
.pp-carousel__dots { display: flex; justify-content: center; gap: 6px; margin-top: 8px; }
.pp-carousel__dot { width: 7px; height: 7px; padding: 0; border: none; border-radius: 50%; background: #d1d5db; cursor: pointer; }
.pp-carousel__dot--active { background: #3b82f6; }
.pp-carousel--single .pp-carousel__nav, .pp-carousel--single .pp-carousel__dots { display: none; }
.pp-risk-ack { display: flex; align-items: center; flex-wrap: wrap; gap: 2px 10px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee; }
.pp-risk-ack__text { flex: 1; min-width: 180px; font-size: 13px; color: #4b5563; }
.pp-risk-ack__input { width: 20px; height: 20px; margin: 0; flex: 0 0 auto; cursor: pointer; accent-color: #3b82f6; }
.pp-risk-ack__input:disabled { cursor: default; }
/* :checked plutot que la classe --done : la coche est verte des qu'elle est cochee, qu'on vienne
   de cliquer ou que le serveur l'ait pre-cochee */
.pp-risk-ack__input:checked { accent-color: #047857; pointer-events: none; }
/* flex-basis 100% : la consigne passe sur sa propre ligne, sous le texte de prise de connaissance */
.pp-risk-ack__hint { display: none; align-items: center; gap: 5px; flex-basis: 100%; font-size: 11px; color: #92400e; }
/* Tant que toutes les photos ne sont pas vues : bouton verrouille, on explique pourquoi */
.pp-risk-ack--locked .pp-risk-ack__hint { display: flex; }
.pp-risk-ack--done .pp-risk-ack__hint { display: none; }
.pp-risk-ack--done { border-top-color: #a7f3d0; }
/* Le theme ne definit pas .hidden sur cette page publique */
.pp-risks-pending.hidden, .pp-inline-signature.hidden { display: none; }
.pp-signatory-media-row { margin: 6px 0 12px; padding: 10px 12px; border: 1px dashed #d1d5db; border-radius: 6px; background: #fafafa; }
.pp-signatory-media-row__label { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #666; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.pp-signatory-media-row__label i { color: #3b82f6; }
.pp-cert-uploads { display: flex; flex-direction: column; gap: 10px; margin-top: 8px; }
.pp-cert-upload { padding: 8px 10px; border: 1px solid #e5e5e5; border-radius: 6px; background: #fff; }
.pp-cert-upload__label { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 6px; }
.pp-cert-upload__label i { color: #3b82f6; }
.pp-cert-upload__row { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
.pp-cert-upload__row .linked-medias { margin: 0; }
.pp-cert-upload__row .photo { border-radius: 6px; }
.pp-public-badge--muted { background: #6b7280; }
.pp-cert-upload .pp-cert-badge-not-concerned { display: none; }
.pp-cert-upload--not-concerned .pp-cert-badge-mandatory { display: none; }
.pp-cert-upload--not-concerned .pp-cert-badge-not-concerned { display: inline-block; }
.pp-cert-not-concerned-btn { display: inline-flex; align-items: center; gap: 6px; margin-left: auto; padding: 4px 10px; font-size: 12px; font-weight: 600; color: #4b5563; background: #fff; border: 1px solid #d1d5db; border-radius: 14px; cursor: pointer; }
.pp-cert-not-concerned-btn:hover { border-color: #9ca3af; background: #f9fafb; }
.pp-cert-not-concerned-btn--active { color: #fff; background: #6b7280; border-color: #6b7280; }
.pp-cert-upload--not-concerned { background: #f9fafb; }
.pp-cert-upload--not-concerned .pp-cert-upload__label { color: #6b7280; }
.pp-cert-upload--not-concerned .pp-cert-upload__row { display: none; }
.pp-mandatory-pending { display: flex; align-items: flex-start; gap: 8px; margin: 12px 0; padding: 12px 14px; font-size: 13px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; }
.pp-mandatory-pending i { margin-top: 2px; }
.pp-mandatory-pending__list { margin: 4px 0 0; padding-left: 18px; font-weight: 600; }
.pp-single-person { margin-top: 16px; }
.pp-inline-signature { margin: 12px 0; padding: 14px; background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; }
.pp-inline-signature__title { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; color: #333; margin-bottom: 10px; }
.pp-inline-signature__title i { color: #3b82f6; }
.pp-inline-signature .signature-element { position: relative; display: block; }
.pp-inline-canvas { width: 100%; height: 200px; touch-action: none; }
.pp-inline-signature__actions { margin-top: 10px; text-align: right; }
.pp-signed-confirm { display: flex; align-items: center; gap: 8px; margin: 12px 0; padding: 14px; font-size: 14px; font-weight: 600; color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; }
.pp-invalid-link { display: flex; align-items: center; gap: 8px; margin: 16px 0; padding: 14px; font-size: 14px; font-weight: 600; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; }
</style>

<div class="pp-public-info">
    <?php foreach ($ppRisks as $ppRiskIndex => $ppRiskItem) { ?>
    <div class="pp-risk-block">
        <div class="pp-risk-block__header">
            <?php if (!empty($ppRiskItem['thumb'])) { ?><img class="pp-risk-block__picto" src="<?php echo $ppRiskItem['thumb']; ?>" alt=""><?php } ?>
            <div>
                <div class="pp-risk-block__name"><?php echo dol_escape_htmltag(($ppRiskItem['name'] != -1) ? $ppRiskItem['name'] : ''); ?></div>
                <?php if (dol_strlen($ppRiskItem['comment'])) { ?><div class="pp-risk-block__comment"><?php echo dol_escape_htmltag($ppRiskItem['comment']); ?></div><?php } ?>
            </div>
            <span class="pp-risk-block__step"><?php echo $langs->trans('SpreadRiskStep', $ppRiskIndex + 1, count($ppRisks)); ?></span>
        </div>

        <?php if (!empty($ppRiskItem['protections'])) { ?>
        <div class="pp-risk-block__section">
            <div class="pp-risk-block__label"><i class="fas fa-hard-hat"></i> <?php echo $langs->trans('MobilePPProtections'); ?></div>
            <div class="pp-risk-protections">
                <?php foreach ($ppRiskItem['protections'] as $ppRiskProtection) { ?>
                <div class="pp-risk-protection" title="<?php echo dol_escape_htmltag($ppRiskProtection['name'] . (dol_strlen($ppRiskProtection['comment']) ? ' - ' . $ppRiskProtection['comment'] : '')); ?>">
                    <img src="<?php echo $ppRiskProtection['thumb']; ?>" alt="<?php echo dol_escape_htmltag($ppRiskProtection['name']); ?>">
                </div>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <?php if (!empty($ppRiskItem['photos'])) { ?>
        <div class="pp-risk-block__section">
            <div class="pp-risk-block__label"><i class="fas fa-camera"></i> <?php echo $langs->trans('SpreadRiskPhotos'); ?></div>
            <div class="pp-carousel <?php echo (count($ppRiskItem['photos']) < 2) ? 'pp-carousel--single' : ''; ?>" data-carousel="<?php echo (int) $ppRiskIndex; ?>">
                <div class="pp-carousel__track">
                    <?php foreach ($ppRiskItem['photos'] as $ppRiskPhoto) { ?>
                    <div class="pp-carousel__slide"><img src="<?php echo $ppRiskPhoto; ?>" alt="" loading="lazy"></div>
                    <?php } ?>
                </div>
                <div class="pp-carousel__counter">1 / <?php echo count($ppRiskItem['photos']); ?></div>
                <button type="button" class="pp-carousel__nav pp-carousel__nav--prev" aria-label="<?php echo dol_escape_htmltag($langs->trans('Previous')); ?>"><i class="fas fa-chevron-left"></i></button>
                <button type="button" class="pp-carousel__nav pp-carousel__nav--next" aria-label="<?php echo dol_escape_htmltag($langs->trans('Next')); ?>"><i class="fas fa-chevron-right"></i></button>
                <div class="pp-carousel__dots">
                    <?php foreach ($ppRiskItem['photos'] as $ppRiskPhotoIndex => $ppRiskPhoto) { ?>
                    <button type="button" class="pp-carousel__dot <?php echo empty($ppRiskPhotoIndex) ? 'pp-carousel__dot--active' : ''; ?>" data-slide="<?php echo (int) $ppRiskPhotoIndex; ?>" aria-label="<?php echo (int) $ppRiskPhotoIndex + 1; ?>"></button>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php } ?>

        <?php
        // Acknowledgement: unlocked once every photo of the risk has been seen, and required
        // before signing. Persisted per signatory so a reload does not undo the reading.
        $riskAcknowledged = in_array((int) $ppRiskItem['category'], $ppAcknowledgedRisks, true);
        ?>
        <div class="pp-risk-ack <?php echo $riskAcknowledged ? 'pp-risk-ack--done' : ''; ?>" data-risk-category="<?php echo (int) $ppRiskItem['category']; ?>" data-photos="<?php echo count($ppRiskItem['photos']); ?>">
            <span class="pp-risk-ack__text"><?php echo $langs->trans('SpreadRiskAcknowledgeText'); ?></span>
            <input type="checkbox" class="pp-risk-ack__input" title="<?php echo dol_escape_htmltag($langs->trans('SpreadRiskAcknowledgeButton')); ?>"<?php echo $riskAcknowledged ? ' checked' : ''; ?>>
            <span class="pp-risk-ack__hint"><i class="fas fa-images"></i> <?php echo $langs->trans('SpreadRiskSeeAllPhotos'); ?></span>
        </div>
    </div>
    <?php } ?>

    <?php if (!empty($ppOrphanProtections)) { ?>
    <div class="pp-public-block">
        <div class="pp-public-block__title"><i class="fas fa-hard-hat"></i> <?php echo $langs->trans('MobilePPProtections'); ?></div>
        <div class="pp-risk-protections">
            <?php foreach ($ppOrphanProtections as $ppOrphanProtection) { ?>
            <div class="pp-risk-protection" title="<?php echo dol_escape_htmltag($ppOrphanProtection['name'] . (dol_strlen($ppOrphanProtection['comment']) ? ' - ' . $ppOrphanProtection['comment'] : '')); ?>">
                <img src="<?php echo $ppOrphanProtection['thumb']; ?>" alt="<?php echo dol_escape_htmltag($ppOrphanProtection['name']); ?>">
            </div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

    <?php
    // Recapitulatif : retrouver d'un coup d'oeil, apres avoir fait defiler tous les blocs, la
    // liste de ce a quoi on s'expose
    if (!empty($ppRisks)) { ?>
    <div class="pp-public-block">
        <div class="pp-public-block__title"><i class="fas fa-clipboard-list"></i> <?php echo $langs->trans('SpreadRecapTitle'); ?></div>
        <div class="pp-recap__line pp-recap__line--risks">
            <?php foreach ($ppRisks as $ppRecapRisk) {
                if (empty($ppRecapRisk['thumb'])) {
                    continue;
                }
                $ppRecapRiskName = ($ppRecapRisk['name'] != -1) ? $ppRecapRisk['name'] : '';
            ?>
            <img src="<?php echo $ppRecapRisk['thumb']; ?>" title="<?php echo dol_escape_htmltag($ppRecapRiskName); ?>" alt="<?php echo dol_escape_htmltag($ppRecapRiskName); ?>">
            <?php } ?>
        </div>

        <?php if (!empty($ppRecapProtections)) { ?>
        <div class="pp-recap__line pp-recap__line--protections">
            <?php foreach ($ppRecapProtections as $ppRecapProtection) { ?>
            <img src="<?php echo $ppRecapProtection['thumb']; ?>" title="<?php echo dol_escape_htmltag($ppRecapProtection['name']); ?>" alt="<?php echo dol_escape_htmltag($ppRecapProtection['name']); ?>">
            <?php } ?>
        </div>
        <?php } ?>
    </div>
    <?php } ?>
</div>

<script>
// Carousel: horizontal scroll-snap driven by the arrows and the dots, one instance per risk block
$(document).ready(function() {
    $('.pp-carousel').each(function() {
        var carousel = $(this);
        var track    = carousel.find('.pp-carousel__track');
        var slides   = carousel.find('.pp-carousel__slide');

        var currentIndex = function() {
            return track.width() ? Math.round(track.scrollLeft() / track.width()) : 0;
        };

        // The right arrow beats as long as there is something left to see, and both arrows fade
        // out once they cannot go any further
        var refresh = function() {
            var index = currentIndex();
            carousel.find('.pp-carousel__counter').text((index + 1) + ' / ' + slides.length);
            window.ppRiskAck.markSeen(carousel.closest('.pp-risk-block').find('.pp-risk-ack').data('risk-category'), index);
            carousel.find('.pp-carousel__dot').removeClass('pp-carousel__dot--active').eq(index).addClass('pp-carousel__dot--active');
            carousel.find('.pp-carousel__nav--prev').toggleClass('pp-carousel__nav--idle', index <= 0);
            carousel.find('.pp-carousel__nav--next')
                .toggleClass('pp-carousel__nav--idle', index >= slides.length - 1)
                .toggleClass('pp-carousel__nav--pulse', index < slides.length - 1);
        };

        var goTo = function(index) {
            index = Math.max(0, Math.min(index, slides.length - 1));
            track.animate({ scrollLeft: index * track.width() }, 200, refresh);
        };

        carousel.find('.pp-carousel__nav--prev').on('click', function() { goTo(currentIndex() - 1); });
        carousel.find('.pp-carousel__nav--next').on('click', function() { goTo(currentIndex() + 1); });
        carousel.find('.pp-carousel__dot').on('click', function() { goTo(parseInt($(this).data('slide'), 10) || 0); });

        // Keep everything in sync when the visitor swipes the track directly
        track.on('scroll', refresh);
        refresh();
    });

    window.ppRiskAck.init();
});

/**
 * Acknowledgement of the risks: the OK button of a risk unlocks once every one of its photos has
 * been seen, and the signature stays locked until every risk has been acknowledged.
 */
window.ppRiskAck = {
    signatoryId: <?php echo !empty($signSignatory) ? (int) $signSignatory->id : 0; ?>,

    // Photos already displayed, per risk block
    seen: {},

    init: function() {
        $('.pp-risk-ack').each(function() {
            var block = $(this);
            window.ppRiskAck.seen[block.data('risk-category')] = {};
            // A risk without photo has nothing to scroll through, its button is available at once
            window.ppRiskAck.markSeen(block.data('risk-category'), 0);
        });

        $('.pp-risk-ack__input').on('change', window.ppRiskAck.acknowledge);
        window.ppRiskAck.refreshSignature();
    },

    /**
     * Record a photo as seen and unlock the button once the visitor has been through them all.
     */
    markSeen: function(riskCategory, slideIndex) {
        var block = $('.pp-risk-ack[data-risk-category="' + riskCategory + '"]');
        if (!block.length || block.hasClass('pp-risk-ack--done')) {
            return;
        }

        window.ppRiskAck.seen[riskCategory] = window.ppRiskAck.seen[riskCategory] || {};
        window.ppRiskAck.seen[riskCategory][slideIndex] = true;

        var total   = parseInt(block.data('photos'), 10) || 0;
        var allSeen = Object.keys(window.ppRiskAck.seen[riskCategory]).length >= total;

        block.toggleClass('pp-risk-ack--locked', !allSeen);
        block.find('.pp-risk-ack__input').prop('disabled', !allSeen);
    },

    /**
     * Send the acknowledgement, and keep it client side only for a visitor with no signature link.
     */
    acknowledge: function() {
        // On n'enregistre que la coche, jamais la decoche : au clavier la case reste cochee
        if (!$(this).is(':checked')) {
            $(this).prop('checked', true);
            return;
        }

        var block        = $(this).closest('.pp-risk-ack');
        var riskCategory = block.data('risk-category');

        block.addClass('pp-risk-ack--done').removeClass('pp-risk-ack--locked');
        // On force la coche plutot que de desactiver le champ : un input disabled est grise par
        // le navigateur, qui ignore alors accent-color
        block.find('.pp-risk-ack__input').prop('checked', true);
        window.ppRiskAck.refreshSignature();

        if (!window.ppRiskAck.signatoryId) {
            return;
        }

        $.ajax({
            url: document.URL + window.saturne.toolbox.getQuerySeparator(document.URL) + 'action=acknowledge_risk&token=' + window.saturne.toolbox.getToken(),
            type: 'POST',
            processData: false,
            contentType: 'application/json',
            data: JSON.stringify({ signatory_id: window.ppRiskAck.signatoryId, risk_category: riskCategory })
        });
    },

    /**
     * The signature block only opens once every risk has been acknowledged.
     */
    refreshSignature: function() {
        var blocks  = $('.pp-risk-ack');
        var pending = blocks.length - blocks.filter('.pp-risk-ack--done').length;

        $('.pp-risks-pending').toggleClass('hidden', pending === 0).find('.pp-risks-pending__count').text(pending);
        $('.pp-inline-signature').toggleClass('hidden', pending > 0);
    }
};
</script>
