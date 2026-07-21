// Groupe de cases à cocher pour assigner des types_cours/types_formation (relations N-N).
export default function CheckboxGroup({ options, selected, onChange }) {
  function toggle(id) {
    onChange(selected.includes(id) ? selected.filter((v) => v !== id) : [...selected, id]);
  }

  return (
    <div className="checkbox-group">
      {options.map((o) => (
        <label key={o.id} className="checkbox-group__item">
          <input
            type="checkbox"
            checked={selected.includes(o.id)}
            onChange={() => toggle(o.id)}
          />
          {o.nom}
        </label>
      ))}
      {options.length === 0 && <p>Aucun type disponible.</p>}
    </div>
  );
}
