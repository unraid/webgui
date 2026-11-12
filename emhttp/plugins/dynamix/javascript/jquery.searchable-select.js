/* Copyright 2026, Lime Technology. Licensed under GPL-2.0. */
(function($) {
  'use strict';

  if (!$.widget || !$.fn.autocomplete) return;

  $.widget('ui.searchableSelect', {
    options: {
      label:null,
      buttonLabel:'Show options'
    },

    _create:function() {
      var select = this.element;
      if (!select.is('select') || select.prop('multiple')) return;
      var self = this;
      this.originalDisplay = select[0].style.display;
      this.picker = $('<span class="searchable-select"></span>').insertAfter(select);
      this.input = $('<input type="text" class="lock searchable-select-input" autocomplete="off" spellcheck="false">')
        .attr({role:'combobox', 'aria-autocomplete':'list', 'aria-expanded':'false'})
        .appendTo(this.picker);
      this.button = $('<button type="button"></button>')
        .attr('aria-label',this.options.buttonLabel).appendTo(this.picker);

      this.input.autocomplete({
        minLength:0,
        source:function(request,response) {
          var term = request.term.toLowerCase().replace(/_/g,' ');
          response(select.find('option').map(function() {
            if (this.disabled || $(this).parent('optgroup').prop('disabled')) return null;
            var label = $(this).text();
            if ((label+' '+this.value).toLowerCase().replace(/_/g,' ').indexOf(term) == -1) return null;
            return {label:label, value:this.value, option:this};
          }).get());
        },
        focus:function() {
          self.input.attr('aria-activedescendant',self.menu.find('.ui-state-active').attr('id'));
          return false;
        },
        select:function(event,ui) {
          self.input.val(ui.item.label);
          if (!ui.item.option.selected) {
            ui.item.option.selected = true;
            select.trigger('change');
          }
          return false;
        },
        change:function() { self.refresh(); },
        open:function() {
          self.menu.find('.ui-menu-item-wrapper').attr('role','option');
          self.input.attr('aria-expanded','true');
        },
        close:function() {
          self.input.attr('aria-expanded','false').removeAttr('aria-activedescendant');
        }
      });
      this.menu = this.input.autocomplete('widget').addClass('searchable-select-options').attr('role','listbox');
      this.input.attr('aria-controls',this.menu.attr('id'));
      this.input.autocomplete('instance')._resizeMenu = function() {
        self.menu.outerWidth(self.picker.outerWidth());
      };

      this._on(this.input, {
        focus:function(event) {
          event.target.select();
          this.input.autocomplete('search','');
        },
        change:function(event) {
          // Query text must not mark the containing settings form as changed.
          event.stopPropagation();
        },
        keydown:function(event) {
          if (event.key == 'Enter') event.preventDefault();
        }
      });
      this._on(this.button, {
        mousedown:function(event) {
          event.preventDefault();
          event.stopPropagation();
        },
        click:function() {
          if (this.menu.is(':visible')) {
            this.input.autocomplete('close');
          } else {
            this.input.trigger('focus');
            this.input.autocomplete('search','');
          }
        }
      });
      this._on(true,select, {
        change:'refresh',
        invalid:function(event) {
          event.preventDefault();
          this.input[0].setCustomValidity(select[0].validationMessage);
          this.input[0].reportValidity();
        }
      });
      this._on($(select[0].labels), {
        click:function(event) {
          event.preventDefault();
          this.input.trigger('focus');
        }
      });
      if (select[0].form) {
        this._on($(select[0].form), {
          reset:function() {
            clearTimeout(this.resetTimer);
            this.resetTimer = this._delay('refresh');
          }
        });
      }
      if (this.options.disabled) select.prop('disabled',true);
      this.refresh();
      select.hide();
    },

    refresh:function() {
      if (!this.input) return;
      var select = this.element;
      this.options.disabled = select.prop('disabled');
      var labelledBy = select.attr('aria-labelledby');
      var label = this.options.label || select.attr('aria-label') ||
        $(select[0].labels).map(function() { return $(this).text().trim(); }).get().join(' ') ||
        select.attr('name') || 'Select an option';
      this.input.removeAttr('aria-label aria-labelledby');
      if (labelledBy && !this.options.label) this.input.attr('aria-labelledby',labelledBy);
      else this.input.attr('aria-label',label);
      this.input.val(select.find(':selected').text())
        .attr('aria-required',String(select.prop('required')))
        .prop('disabled',select.prop('disabled'));
      this.input[0].setCustomValidity('');
      this.button.attr('aria-label',this.options.buttonLabel).prop('disabled',select.prop('disabled'));
      this.input.autocomplete('close');
    },

    _setOption:function(key,value) {
      this._super(key,value);
      if (key == 'disabled') this.element.prop('disabled',value);
      this.refresh();
    },

    _destroy:function() {
      if (!this.input) return;
      clearTimeout(this.resetTimer);
      this.input.autocomplete('destroy');
      this.picker.remove();
      this.element.css('display',this.originalDisplay);
    }
  });
})(jQuery);
