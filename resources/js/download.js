/**
 * Download a Blob without window.open(blob:…), which some browsers rewrite into
 * broken URLs like https://https//blob:http://…
 */
export function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename || 'download';
  a.rel = 'noopener';
  document.body.appendChild(a);
  a.click();
  a.remove();
  // Revoke after the click is processed.
  setTimeout(() => URL.revokeObjectURL(url), 1500);
}

/** Open a Blob in a same-tab object URL preview (img/pdf), never via window.open. */
export function blobObjectUrl(blob) {
  return URL.createObjectURL(blob);
}
