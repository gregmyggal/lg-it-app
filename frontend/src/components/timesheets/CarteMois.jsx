import StatutBadge from '../ui/StatutBadge';
import { ETATS_ENCODAGE, STATUTS_TIMESHEET, TYPES_ACTIVITE, getStatut } from '../../utils/statuts';
import { formatDateCourte, formatHoraire } from '../../utils/dates';
import { formatDuree, formatEuros, formatHeures } from '../../utils/format';
import { libelleSession, libelleSessionPhrase } from '../../utils/classes';

function ActionsSaisie({ saisie, verrouille, onModifier, onSupprimer }) {
  if (verrouille || (!saisie.can?.update && !saisie.can?.delete)) return null;
  return (
    <div className="mm-actions">
      {saisie.can?.update && (
        <button type="button" className="mm-btn mm-btn--soft" onClick={() => onModifier(saisie)}>
          Modifier
        </button>
      )}
      {saisie.can?.delete && (
        <button type="button" className="mm-btn mm-btn--soft" onClick={() => onSupprimer(saisie)}>
          Supprimer
        </button>
      )}
    </div>
  );
}

function LigneSaisie({ saisie, verrouille, onModifier, onSupprimer }) {
  return (
    <div className="mm-saisie">
      <div className="mm-saisie__ligne">
        <span>{getStatut(TYPES_ACTIVITE, saisie.type_activite).label}</span>
        <strong>{formatHeures(saisie.nombre_heures)}</strong>
        <span className="mm-montant">{formatEuros(saisie.montant_brut)}</span>
        <StatutBadge table={STATUTS_TIMESHEET} valeur={saisie.statut_validation} />
      </div>
      <ActionsSaisie saisie={saisie} verrouille={verrouille} onModifier={onModifier} onSupprimer={onSupprimer} />
    </div>
  );
}

/**
 * Une carte du mois (UI-M4) : une séance (à encoder en 1 tap, encodée, annulée, remplacée) ou des heures libres.
 * Une seule action principale par carte ; tout l'état vient du serveur (`encodage`, `peut_encoder`, `can.*`, `mois_soumis`).
 */
export default function CarteMois({ carte, verrouille, occupe, onEncoder, onAutreDuree, onModifier, onSupprimer }) {
  if (carte.type === 'libre') {
    const x = carte.donnees;
    return (
      <li className="mm-carte mm-carte--libre">
        <div className="mm-carte__titre">{x.cours?.titre ? `${getStatut(TYPES_ACTIVITE, x.type_activite).label} · ${x.cours.titre}` : getStatut(TYPES_ACTIVITE, x.type_activite).label}</div>
        <div className="mm-carte__meta">{formatDateCourte(carte.date)} · heures ajoutées</div>
        <LigneSaisie saisie={x} verrouille={verrouille} onModifier={onModifier} onSupprimer={onSupprimer} />
      </li>
    );
  }

  const s = carte.donnees;
  const saisies = s.mes_timesheets;
  const aEncoder = s.encodage === 'a_encoder' && s.peut_encoder && !verrouille;
  const nom = `${s.classe_libelle} ${libelleSessionPhrase(s)}`;

  return (
    <li className={`mm-carte${s.annulee ? ' mm-carte--annulee' : ''}`}>
      <div className="mm-carte__titre">
        {s.classe_libelle} · {libelleSession(s)}
      </div>
      <div className="mm-carte__meta">
        <span>{formatDateCourte(s.date)}</span>
        <span>{formatHoraire(s.heure_debut, s.heure_fin)}</span>
        {s.est_a_venir && !s.annulee && <StatutBadge label="À venir" tone="primary" />}
        {s.annulee && <StatutBadge label="Annulée" tone="error" />}
        {s.remplace_par && <StatutBadge label={`Remplacée par ${s.remplace_par.nom}`} tone="neutral" />}
      </div>

      {saisies.map((x) => (
        <LigneSaisie key={x.id} saisie={x} verrouille={verrouille} onModifier={onModifier} onSupprimer={onSupprimer} />
      ))}

      {saisies.length === 0 && s.annulee && <p className="mm-note">Annulée : aucune heure à encoder.</p>}
      {saisies.length === 0 && !s.annulee && !s.peut_encoder && <p className="mm-note">Vous ne participez pas à cette séance : rien à encoder.</p>}
      {saisies.length === 0 && !s.annulee && s.peut_encoder && verrouille && <StatutBadge table={ETATS_ENCODAGE} valeur="a_encoder" />}

      {aEncoder && saisies.length === 0 && (
        <div className="mm-actions">
          <button type="button" className="mm-btn mm-btn--cta" disabled={occupe} aria-label={`Encoder ${formatDuree(s.duree_par_defaut)} — ${nom}`} onClick={() => onEncoder(s)}>
            Encoder {formatDuree(s.duree_par_defaut)}
          </button>
          <button type="button" className="mm-lien" aria-label={`Autre durée — ${nom}`} onClick={() => onAutreDuree(s)}>
            Autre durée
          </button>
        </div>
      )}
    </li>
  );
}
