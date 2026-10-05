import { ADMIN_COLORS, ADMIN_RADIUS, ADMIN_SPACING, ADMIN_TONES } from '../../styles/AdminDesignSystem';

/**
 * Récapitulatif de propagation renvoyé par l'API (aperçu) : « N sessions, P passées non modifiées ».
 * L'assignation couvre les sessions à venir et les sessions passées sans aucun professeur ni heure (CLS-07) ;
 * une session déjà assignée n'est pas dupliquée.
 */
export default function RecapitulatifPropagation({ apercu }) {
  const n = apercu.sessions_assignees;
  const passees = apercu.sessions_passees_ignorees;
  const deja = apercu.sessions_deja_assignees;

  return (
    <div
      style={{
        background: ADMIN_TONES.info.bg,
        color: ADMIN_TONES.info.fg,
        border: `1px solid ${ADMIN_TONES.info.border}`,
        borderRadius: ADMIN_RADIUS.md,
        padding: ADMIN_SPACING.lg,
      }}
    >
      <strong>
        {n === 0
          ? 'Aucune session à assigner.'
          : `${n} session${n > 1 ? 's' : ''} ${n > 1 ? 'seront assignées' : 'sera assignée'}.`}
      </strong>
      <ul style={{ margin: `${ADMIN_SPACING.sm} 0 0`, paddingLeft: '20px', color: ADMIN_COLORS.textPrimary }}>
        <li>
          {passees} session{passees > 1 ? 's' : ''} passée{passees > 1 ? 's' : ''} non modifiée{passees > 1 ? 's' : ''} (elles ont déjà un
          professeur ou des heures)
        </li>
        <li>Les sessions passées sans aucun professeur ni heure sont aussi assignées.</li>
        {deja > 0 && (
          <li>
            {deja} session{deja > 1 ? 's' : ''} déjà assignée{deja > 1 ? 's' : ''} (aucun doublon)
          </li>
        )}
        <li>Les sessions annulées sont ignorées ; les sessions ajoutées plus tard héritent des professeurs de la classe.</li>
      </ul>
    </div>
  );
}
