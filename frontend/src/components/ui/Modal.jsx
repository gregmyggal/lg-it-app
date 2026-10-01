export default function Modal({ children, onClose }) {
  return (
    <>
      <div className="modal-overlay" onClick={onClose} />
      <div className="modal-container">
        <div className="modal">
          <button className="modal__close" onClick={onClose} aria-label="Fermer">
            ×
          </button>
          {children}
        </div>
      </div>
    </>
  );
}
