/* ---------- HealthFlow: Log Exercise Module ---------- */

(() => {
  const post = async (fd) => {
    const res = await fetch('log_exercise.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data && data.ok) location.reload();
  };

  const errorBox = document.getElementById('log-exercise-error');
  const durationInput = document.getElementById('log-exercise-duration');
  const logBtn = document.getElementById('btn-log-exercise');
  let selectedExercise = null;

  const selectExercise = (btn) => {
    selectedExercise = btn;
    document.querySelectorAll('[data-action="exercise"]').forEach(b => {
      b.classList.toggle('preset-btn--selected', b === btn);
    });
    if (errorBox) errorBox.style.display = 'none';
  };

  document.querySelectorAll('[data-action="exercise"]').forEach(btn => {
    btn.addEventListener('click', () => selectExercise(btn));
  });

  const logExercise = () => {
    if (!selectedExercise) {
      if (errorBox) errorBox.style.display = 'block';
      return;
    }
    const fd = new FormData();
    fd.append('action', 'exercise');
    fd.append('exercise_type', selectedExercise.dataset.type);
    fd.append('duration_min', durationInput?.value || 10);
    post(fd);
  };

  logBtn?.addEventListener('click', logExercise);
  durationInput?.addEventListener('keydown', (e) => { if (e.key === 'Enter') logExercise(); });
})();