/**
 * Created by s.vetlovskiy on 09.04.14.
 */

$('.notifications').find('.close').click(function(event){
	$(event.target).parent().remove();
});
