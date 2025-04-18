import {SortableContainer, SortableElement, SortableHandle} from 'react-sortable-hoc';
import React from 'react'
export default React.createClass({
    onSortEnd(list,func,{oldIndex, newIndex},e){
        func(list[oldIndex].props.id,list[newIndex].props.id);
        $(e.target).parent().removeClass('as_dragged');
    },
    onSortStart({oldIndex, newIndex},e){
        $(e.target).parent().addClass('as_dragged');
    },
    render() {
        const list = this.props.list || [] //list of objects
        const axis = this.props.axis || 'y'
        const useDragHandle = this.props.useDragHandle  || 'false'
        const handleComponent = this.props.handleComponent  || <div></div>
        const maxHeight = this.props.maxHeight;
        const maxWidth = this.props.maxWidth;
        const lockAxis = this.props.lockAxis || ''
        const func = this.props.func // callback function
        const DragHandle = SortableHandle(() =>handleComponent); // This can be any component you want
        const SortableItem = SortableElement(({value}) => <li>{useDragHandle?<DragHandle/>:''}{value}</li>);
        const SortableList = SortableContainer(({items}) => {
            return (
                <ul className='sortable'>
                    {items.map((value, index) =>
                        <SortableItem key={`item-${index}`} index={index} id={index} value={value} />
                    )}
                </ul>
            );
        });
        return (
            <SortableList
                items={list}
                axis = {axis}
                lockAxis={lockAxis}
                helperClass = 'sortableHelperClass'
                getHelperDimensions = {({node})=> {
                    
                    var width = maxWidth || node.offsetWidth || 400,
                        height = maxHeight || node.offsetHeight || 100;

                    return {width,height}
                }}
                useWindowAsScrollContainer = {true}
                onSortEnd={this.onSortEnd.bind(null,list,func)}
                onSortStart={this.onSortStart}
                useDragHandle={useDragHandle}/>
            )
    }
})
