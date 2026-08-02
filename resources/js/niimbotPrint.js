/**
 * Direct NiimBot printing via Web Bluetooth (@mmote/niimbluelib).
 * Requires Chrome/Edge on HTTPS or localhost.
 *
 * D11/D110 use printDirection "left": encoded columns = canvas.height = printhead (96),
 * so the PNG must be landscape (feed length × tape width), not portrait.
 *
 * CRITICAL: ImageEncoder treats ANY non-#FFFFFF pixel as black. Anti-aliased greys
 * from canvas scaling become solid blobs — always nearest-neighbour + hard threshold.
 */

const LABEL_SIZE_KEY = 'depot_label_size';

export function bluetoothPrintSupported() {
  return typeof navigator !== 'undefined' && !!navigator.bluetooth;
}

export function loadSavedLabelSize(fallback = 'standard') {
  try {
    return localStorage.getItem(LABEL_SIZE_KEY) || fallback;
  } catch {
    return fallback;
  }
}

export function saveLabelSize(size) {
  try {
    localStorage.setItem(LABEL_SIZE_KEY, size);
  } catch {
    // ignore quota / private mode
  }
}

function loadImage(url) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error('Could not decode the label image.'));
    img.src = url;
  });
}

function roundUpToMultiple(value, multiple) {
  const n = Math.max(multiple, Math.round(value));
  const rem = n % multiple;
  return rem === 0 ? n : n + (multiple - rem);
}

/** Force pure black/white — greys become solid black on the printer. */
function binarizeCanvas(canvas, threshold = 200) {
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  const { width, height } = canvas;
  const imageData = ctx.getImageData(0, 0, width, height);
  const d = imageData.data;
  for (let i = 0; i < d.length; i += 4) {
    const lum = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
    const v = lum < threshold ? 0 : 255;
    d[i] = v;
    d[i + 1] = v;
    d[i + 2] = v;
    d[i + 3] = 255;
  }
  ctx.putImageData(imageData, 0, 0);
  return canvas;
}

/**
 * Fit the label PNG onto a canvas sized for the connected printer.
 *
 * - printDirection "left" (D11/D110): canvas height = printhead, width = feed length
 * - printDirection "top" (B1…): canvas width = printhead, height = feed length
 */
export async function blobToPrinterCanvas(blob, meta = {}) {
  const url = URL.createObjectURL(blob);
  try {
    const img = await loadImage(url);
    const printDirection = meta.printDirection || 'left';
    const printhead = meta.printheadPixels || (printDirection === 'left' ? 96 : 384);
    const aspect = img.width / Math.max(1, img.height);

    let canvasW;
    let canvasH;
    if (printDirection === 'left') {
      // Prefer exact PNG height when it already matches the printhead (no soft scale).
      if (img.height === printhead || img.height === roundUpToMultiple(printhead, 8)) {
        canvasH = roundUpToMultiple(img.height, 8);
        canvasW = roundUpToMultiple(img.width, 8);
      } else {
        canvasH = roundUpToMultiple(printhead, 8);
        canvasW = roundUpToMultiple(canvasH * aspect, 8);
      }
    } else if (img.width === printhead || img.width === roundUpToMultiple(printhead, 8)) {
      canvasW = roundUpToMultiple(img.width, 8);
      canvasH = roundUpToMultiple(img.height, 8);
    } else {
      canvasW = roundUpToMultiple(printhead, 8);
      canvasH = roundUpToMultiple(canvasW / aspect, 8);
    }

    const canvas = document.createElement('canvas');
    canvas.width = canvasW;
    canvas.height = canvasH;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvasW, canvasH);
    ctx.imageSmoothingEnabled = false;

    // Integer nearest-neighbour contain (avoid subpixel blur).
    const scale = Math.min(canvasW / img.width, canvasH / img.height);
    const drawW = Math.max(1, Math.round(img.width * scale));
    const drawH = Math.max(1, Math.round(img.height * scale));
    const dx = Math.floor((canvasW - drawW) / 2);
    const dy = Math.floor((canvasH - drawH) / 2);
    ctx.drawImage(img, dx, dy, drawW, drawH);

    binarizeCanvas(canvas);
    return { canvas, printDirection };
  } finally {
    URL.revokeObjectURL(url);
  }
}

/** @deprecated use blobToPrinterCanvas */
export async function blobToCanvas(blob, options = {}) {
  const { canvas } = await blobToPrinterCanvas(blob, {
    printDirection: 'top',
    printheadPixels: options.targetWidth || 384,
  });
  return canvas;
}

let sharedClient = null;

/**
 * Connect (or reuse) a NiimBot over Web Bluetooth and print one PNG blob.
 * @param {Blob} pngBlob
 * @param {{ onStatus?: (msg: string) => void, fallbackTask?: string }} [options]
 */
export async function printPngBlobToNiimbot(pngBlob, options = {}) {
  if (!bluetoothPrintSupported()) {
    throw new Error('NiimBot printing needs Chrome or Edge on HTTPS (or localhost).');
  }

  const { NiimbotBluetoothClient, ImageEncoder, LabelType } = await import('@mmote/niimbluelib');

  options.onStatus?.('Connecting to NiimBot…');
  if (!sharedClient?.isConnected()) {
    sharedClient = new NiimbotBluetoothClient();
    sharedClient.setPacketInterval(2);
    await sharedClient.connect();
    await sharedClient.fetchPrinterInfo();
  }

  const meta = sharedClient.getModelMetadata?.() || {};
  const printDirection = meta.printDirection || 'left';
  const taskName = sharedClient.getPrintTaskType?.() || options.fallbackTask || 'D110';

  options.onStatus?.(`Printing via ${taskName} (${printDirection})…`);
  const { canvas } = await blobToPrinterCanvas(pngBlob, meta);
  const encoded = ImageEncoder.encodeCanvas(canvas, printDirection);

  // Die-cut 15×30 rolls use gap sensing; continuous tape still accepts WithGaps on many models.
  const printTask = sharedClient.abstraction.newPrintTask(taskName, {
    totalPages: 1,
    labelType: LabelType?.WithGaps ?? 1,
  });

  try {
    await printTask.printInit();
    await printTask.printPage(encoded, 1);
    await printTask.waitForPageFinished();
    await printTask.waitForFinished();
  } finally {
    try {
      await sharedClient.abstraction.printEnd();
    } catch {
      // printer may already have ended the job
    }
  }

  options.onStatus?.('Printed.');
  return { deviceName: sharedClient.getPrinterInfo?.()?.deviceName || meta?.model };
}

/**
 * Print several PNG blobs sequentially (same Bluetooth session).
 * @param {Blob[]} blobs
 * @param {{ onStatus?: (msg: string) => void, onProgress?: (i: number, total: number) => void, shouldCancel?: () => boolean }} [options]
 */
export async function printPngBlobsToNiimbot(blobs, options = {}) {
  const list = blobs.filter(Boolean);
  if (!list.length) {
    throw new Error('Nothing to print.');
  }

  for (let i = 0; i < list.length; i++) {
    if (options.shouldCancel?.()) {
      throw new Error('Print cancelled.');
    }
    options.onProgress?.(i + 1, list.length);
    options.onStatus?.(`Printing label ${i + 1} of ${list.length}…`);
    await printPngBlobToNiimbot(list[i], { onStatus: options.onStatus });
  }
}

export async function disconnectNiimbot() {
  try {
    await sharedClient?.disconnect?.();
  } catch {
    // ignore
  }
  sharedClient = null;
}
