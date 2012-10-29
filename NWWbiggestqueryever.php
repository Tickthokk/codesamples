<?php
$query = 
	'INSERT INTO object_pricing ' . 
		'(nestId, PriceBasedOn, CartonQty, ListPriceBasedOn, SMarkup, RMarkup, Markup, ' . 
		'List, ListDiscount, Cost, Adjust, AdjustedCost, SPrice, RPrice, WebPrice, QtyDiscount, zeroCost) ' . 
	'SELECT nest.id, \'Cost Plus\', ' . 
		'IFNULL(' . ($inv ? 'invPrice' : 'oPricing') . '.CartonQty, 1) AS CQ, ' . 
		'IFNULL(' . ($inv ? 'invPrice' : 'oPricing') . '.ListPriceBasedOn, \'Item\') AS LPBO, ' . 
		'@DSM := ' . (!$inv ? 'IF(o.ObjectType=2, oPricing.SMarkup, 0) + ' : '') . 'IF(parentO.ObjectType=3, 0, ' . (!$inv ? 'IF(o.ObjectType=3, oPricing.SMarkup, IFNULL(pPricing.SMarkup, 0))' : $DSM) . ') AS DSM, ' . 
		'@DRM := ' . (!$inv ? 'IF(o.ObjectType=2, oPricing.RMarkup, 0) + ' : '') . 'IF(parentO.ObjectType=3, 0, ' . (!$inv ? 'IF(o.ObjectType=3, oPricing.RMarkup, IFNULL(pPricing.RMarkup, 0))' : $DRM) . ') AS DRM, ' . 
		'@DM := ' . (!$inv ? 'IF(o.ObjectType=2, oPricing.Markup, 0) + ' : '') . 'IF(parentO.ObjectType=3, 0, ' . (!$inv ? 'IF(o.ObjectType=3, oPricing.Markup, IFNULL(pPricing.Markup, 0))' : $DM) . ') AS DM, ' . 
		($inv ? 
			#$newCost = $adjustedCost * (1 + (($originDiscount + $additional) / 100));
			'@cost := ROUND(IFNULL(custPrice.WebPrice, ' . 
				'invPrice.AdjustedCost * (1 + ((' .
					'IFNULL(opd.Discount, ' . if_empty($this->settings['UnassignedDiscount'], 0) . ') + ' . 
					'IFNULL(cdl.Additional, 0)' . 
				') / 100))' . 
			'), 2) AS List, 0 AS ListDiscount, ROUND(@cost, 2) AS Cost, ' .
			'@adj := IFNULL(pPricing.Adjust, 0) AS Adjust, @AC := ROUND(@cost + @adj, 2) AS AdjustedCost, ' . 
			'ROUND(@AC * (1 + ((' . $DSM . ' + IFNULL(pPricing.SMarkup, 0)) / 100)), 2) AS SPrice, ' . 
			'ROUND(@AC * (1 + ((' . $DRM . ' + IFNULL(pPricing.RMarkup, 0)) / 100)), 2) AS RPrice, ' . 
			'ROUND(@AC * (1 + ((' . $DM . ' + IFNULL(pPricing.Markup, 0)) / 100)), 2) AS WebPrice' 
		: 
			'oPricing.List, oPricing.ListDiscount, oPricing.Cost, oPricing.Adjust, @AC := oPricing.AdjustedCost, ' . 
			'ROUND(@AC * (1 + ((@DSM + ' . (!$inv ? 'IF(parentO.ObjectType=3, ' : '') . 'IFNULL(pPricing.SMarkup, IFNULL(parentOPricing.SMarkup, 0))' . (!$inv ? ', 0)' : '') . ') / 100)), 2) AS SPrice, ' . 
			'ROUND(@AC * (1 + ((@DRM + ' . (!$inv ? 'IF(parentO.ObjectType=3, ' : '') . 'IFNULL(pPricing.RMarkup, IFNULL(parentOPricing.RMarkup, 0))' . (!$inv ? ', 0)' : '') . ') / 100)), 2) AS RPrice, ' . 
			'ROUND(@AC * (1 + ((@DM + ' . (!$inv ? 'IF(parentO.ObjectType=3, ' : '') . 'IFNULL(pPricing.Markup, IFNULL(parentOPricing.Markup, 0))' . (!$inv ? ', 0)' : '') . 
			($this->meManu ?
				' + (IFNULL(cdl.Additional, 0) + IFNULL(opd.Discount, IFNULL(opdd.Discount, ' . if_empty($this->settings['UnassignedDiscount'], 0) . ')))'
			: '')
			 . ') / 100)), 2) AS WebPrice' 
		) . 
		', ' . ($inv ? 'invPrice' : 'oPricing') . '.QtyDiscount, @AC=0 AS zeroCost ' . 
	'FROM object_nest AS nest ' . 
	'JOIN objects AS o ON o.ID = nest.objectId ' . 
	(!$inv ? 
		'JOIN account_trees AS at1 ON at1.id = nest.treeId ' . 
		'JOIN account_trees AS at ON at.accountId = at1.accountId AND at.type = \'Inventory\' ' . 
		'JOIN object_nest AS nest2 ON nest2.objectId = nest.objectId AND nest2.treeId = at.id ' . 
		'JOIN object_pricing AS oPricing ON oPricing.nestId = nest2.id '
	: '' ) . 
	'LEFT JOIN object_nest AS pnest2 ON pnest2.root = nest' . ($inv ? '' : '2') . '.root ' . 
		'AND nest' . ($inv ? '' : '2') . '.level - 1 = pnest2.level ' . 
		'AND pnest2.l < nest' . ($inv ? '' : '2') . '.l ' . 
		'AND pnest2.r > nest' . ($inv ? '' : '2') . '.r ' . 
	'LEFT JOIN objects AS po2 ON po2.ID = pnest2.objectId ' . 
	'LEFT JOIN object_pricing AS pPricing ON pPricing.nestId = pnest2.id ' . 
	($inv ? 
		'JOIN objects AS originO ON o.InstanceOf = originO.ID ' . 
		'JOIN account_trees AS atInv ON atInv.accountId = originO.Owner AND atInv.type = \'Inventory\' ' . 
		'JOIN account_trees AS atCust ON atCust.accountId = originO.Owner ' .
			'AND atCust.type = \'Customer\' AND atCust.typeId = ' . $this->treeTypeId . ' ' . 
		'JOIN object_nest AS invNest ON atInv.id = invNest.treeId AND invNest.objectId = o.InstanceOf ' . 
		'JOIN object_pricing AS invPrice ON invPrice.nestId = invNest.id ' . 
		'LEFT JOIN object_nest AS custNest ON atCust.id = custNest.treeId AND custNest.objectId = o.InstanceOf ' . 
		'LEFT JOIN object_pricing AS custPrice ON custPrice.nestId = custNest.id ' . 
		'LEFT JOIN customer_discount_levels AS cdl ON cdl.CustomerID = atCust.typeId AND cdl.SetID = invPrice.QtyDiscount ' . 
		'LEFT JOIN object_pricing_discount_levels AS opdl ON opdl.AccountID = atInv.accountId ' .
			'AND opdl.Name = \'Default\' AND opdl.isDefaultSet = 0 AND opdl.Order = 255 ' . 
		'LEFT JOIN object_pricing_discounts AS opd ON opd.ObjectID = o.InstanceOf ' .
			'AND opd.DiscountID = IFNULL(cdl.LevelID, opdl.ID) ' 
	:	($this->meManu ? 
			'LEFT JOIN customer_discount_levels AS cdl ON nest.treeId = cdl.CustomerID AND cdl.SetID = oPricing.QtyDiscount ' . 
			'LEFT JOIN object_pricing_discounts AS opd ON opd.ObjectID = nest.objectId AND opd.DiscountID = cdl.LevelID ' . 
			'LEFT JOIN object_pricing_discount_levels AS opdl ON opdl.AccountID = at.accountId AND opdl.isDefaultSet = 0 AND opdl.Order = 255 ' . 
			'LEFT JOIN object_pricing_discount_defaults AS opdd ON opdd.DiscountSet = oPricing.QtyDiscount AND opdd.DiscountID = opdl.ID '
		: '') 
	) . 
	'LEFT JOIN object_nest AS pnest ON pnest.root = nest.root AND nest.level - 1 = pnest.level ' . 
		'AND pnest.l < nest.l AND pnest.r > nest.r ' . 
	'LEFT JOIN objects AS parentO ON parentO.ID = pnest.objectId ' .  
	'LEFT JOIN object_pricing AS parentOPricing ON pnest.id = parentOPricing.nestId ' . 
	'WHERE o.ObjectType IN (2,3) AND nest.treeId = ' . $this->tree . ' ' . 
		'AND nest.l BETWEEN ' . $newLeft . ' AND ' . $newRight
;
?>
