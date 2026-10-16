import 'fake-indexeddb/auto';
import { afterEach } from 'vitest';

// jsdom has no canvas: a recording 2D context is enough for the unit tests.
HTMLCanvasElement.prototype.getContext = function () {
  return null;
} as unknown as HTMLCanvasElement['getContext'];

if (!URL.createObjectURL) {
  URL.createObjectURL = () => 'blob:mock';
  URL.revokeObjectURL = () => {};
}

afterEach(() => {
  sessionStorage.clear();
  document.body.innerHTML = '';
});
