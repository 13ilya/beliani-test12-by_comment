import React, { Component} from 'react';
import { DragSource, DropTarget } from 'react-dnd';
import { NativeTypes } from 'react-dnd-html5-backend';
import flow from 'lodash/flow';

export const ItemTypes = {
  DragBlock: 'Images',
  DropBlock: [NativeTypes.FILE,'Images']
};

var BlockTarget = {
	hover (){
		return true;
	},
	canDrop () {return true},

	drop(target, monitor) {
		var block_name=target['cell-id'];
		var struct=target.options.value;
		switch (monitor.getItemType()){
		case 'Images':
			var data={
				'sell-id':target['cell-id'],
				'path':struct['path_'+block_name],
				'doc-id':struct['doc_id'],
				'path_id':'path_'+block_name,
			}
			target.onDropImage(target.dragSource,data);
			break;
		case NativeTypes.FILE:
			var id=target['master-id'];
			var inputFile=monitor.getItem();
			inputFile=inputFile['files'][0];
			target.onFile(id,inputFile,block_name,struct);

			break;
	}
	return {}
  }
}

const BlockSource = {
  beginDrag(props) {
    const options=props.options.value;
    var data={
		'sell-id':props['cell-id'],
		'path':options['path_'+props['cell-id']],
		'doc-id':options['doc_id'],
		'path_id':'path_'+props['cell-id'],
		}
	props.onEndDragged(data);
    return{} ;
  }

}
function collectDrag(connect, monitor) {
  return {
    connectDragSource: connect.dragSource(),
    isDragging: monitor.isDragging(),
  }
}
function collectDrop(connect, monitor) {
  return {
  connectDropTarget: connect.dropTarget(),
  isOver: monitor.isOver({shallow: true }),
  canDrop: monitor.canDrop()
}
}
class dropDragBlock extends Component {

  render() {
		const {
			children,
			bsClass,
			connectDragSource,
			connectDropTarget,
			isOver,
			id,
			canDrop,
			} = this.props
		console.log('isOver=',isOver,'canDrop',canDrop);
		return flow(connectDropTarget,connectDragSource)(
		<div
			id={id}
			className={bsClass}
			style={isOver && canDrop?{boxShadow: '0px 0px 5px 3px green'}:{}}>{children}</div>
		);
  }
}

export default flow([
  DragSource(ItemTypes.DragBlock, BlockSource, collectDrag),
   DropTarget(ItemTypes.DropBlock, BlockTarget, collectDrop)]
)(dropDragBlock);
