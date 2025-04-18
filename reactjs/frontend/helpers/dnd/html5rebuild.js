import HTML5Backend from 'react-dnd-html5-backend/lib/HTML5Backend';

class NonNativeHTML5Backend extends HTML5Backend {
  constructor(manager) {
    super(manager);

    // Rebind our overwritten methods
    this.handleTopDrop = this.handleTopDrop.bind(this);
    this.handleTopDropCapture = this.handleTopDropCapture.bind(this);
    this.handleTopDragOver = this.handleTopDragOver.bind(this);
    this.handleTopDragLeaveCapture = this.handleTopDragLeaveCapture.bind(this);
  }

  handleTopDragOver(e) {
    if (this.isDraggingNativeItem()) {
      return;
    }

    super.handleTopDragOver(e);
  }

  handleTopDragLeaveCapture(e) {
    if (this.isDraggingNativeItem()) {
      return;
    }

    super.handleTopDragLeaveCapture(e);
  }

  handleTopDropCapture(e) {
    if (this.isDraggingNativeItem()) {
      return;
    }

    super.handleTopDropCapture(e);
  }

  handleTopDrop(e) {
    if (!this.monitor.isDragging() || this.isDraggingNativeItem()) {
      return;
    }

    super.handleTopDrop(e);
  }
}

export default function createHTML5Backend(manager) {
  return new NonNativeHTML5Backend(manager);
}
