import Autosuggest from 'react-autosuggest';
import React from 'react';
import {fetchFromApi} from './support_functions'

function getSuggestions(list,value) {
  const inputValue = value.trim().toLowerCase();
  const inputLength = inputValue.length;
  return inputLength < 3 ? [] : list.filter(value =>value.label.toLowerCase().indexOf(inputValue) != -1);
}

function getSuggestionValue( entry, func, error, id, suggestion) {
    let ret = '';
    if (suggestion.label == 'No results') return '' ;
    if (error){
        if (confirm('if you change offer, you change aliases too, continue?')){
        ret=suggestion.label;
        func(suggestion.label,suggestion.value);
        }
        else ret=entry;
        }
    else{
        ret = id != 'new' ? suggestion.label : '';
        func(suggestion.label,suggestion.value);

    }
    return ret               // what should be the value of the input
}
function renderSuggestion(suggestion) {
  return (
    <span>{suggestion.label}</span>
  );
}

export default class Autosug extends React.Component {
  constructor() {
    super();
    this.state = {
      value:'Null',
      suggestions: []
    };
  }

  onChange = (event, { newValue }) => {
    this.setState({ value: newValue });
  };

  valueUpdate = (value) => {
    this.setState({value});
  };

  onSuggestionsFetchRequested = (list,url,id,onlyValue,listKey,exclude,{ value }) => {
    const inputLength = value.length;
    if (inputLength>=3 && url){
        const parent = $('#react-autowhatever-'+id).parent('.react-autosuggest__container');
        parent.find('img').remove();
        parent.append('<img src="/images/ajax-loader.gif" class="preloader"/>');
        fetchFromApi({search:value}, url, (data)=>{
            parent.find('img').remove();
            let list_of_values = data[listKey]?data[listKey]:{}
            let suggestions=[],label
            for(let key in list_of_values){
                var itemValue = list_of_values[key] || '';
                label = onlyValue?itemValue : key+':'+itemValue
                suggestions.push({value: key, label})
            }
            if(!suggestions.length){
                suggestions = [{value: '', label:'No results'}]
            }
            if(exclude.key){//delete selected values from list
                var index = 0;
                exclude.list.forEach((item)=>{
                    index = _.findIndex(suggestions, function(listValue) {
                        let param = listValue[exclude.key];
                        return param == item
                    });
                    if(index != -1) suggestions.splice(index,1);
                })
            }
            this.setState({suggestions});
      })
    }
    else this.setState({suggestions: getSuggestions(list,value)});
  };

  onSuggestionsClearRequested = () => {
    this.setState({
      suggestions: []
    });
  };
  componentDidUpdate(){
      const update = this.props.update || false
      const entry = this.props.entry || ''
      const clearUpdate = this.props.clearUpdate;
      if (update){
          this.valueUpdate(update=='entry'? entry:'');
          clearUpdate();
      }
  }
  render() {
    const {list,entry,func,error} = this.props;
    const url = this.props.url ||''
    const id = this.props.id || ''
    const exclude = this.props.exclude || []
    const listKey = this.props.listKey || 'offers';//key in request object
    const onlyValue = this.props.onlyValue || false;//if false, saggestation list have labels like key+value
    let {suggestions,value} = this.state;
    if (value=='Null')value=entry;
    const inputProps = {
      value,
      onChange: this.onChange
    };

    return (
      <Autosuggest
        suggestions={suggestions}
        id={id}
        onSuggestionsUpdateRequested={this.onSuggestionsUpdateRequested}
        onSuggestionsClearRequested={this.onSuggestionsClearRequested}
        getSuggestionValue={getSuggestionValue.bind(null,entry,func,error,id)}
        onSuggestionsFetchRequested = {this.onSuggestionsFetchRequested.bind(null,list,url,id,onlyValue,listKey,exclude)}
        renderSuggestion={renderSuggestion}
        inputProps={inputProps} />
    );
  }
}
