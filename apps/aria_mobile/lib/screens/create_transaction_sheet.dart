import 'package:flutter/material.dart';

import '../api/aria_api.dart';
import 'buy_form_screen.dart';
import 'cash_form_screen.dart';
import 'move_form_screen.dart';
import 'sell_form_screen.dart';
import 'transfer_form_screen.dart';

class CreateTransactionSheet extends StatelessWidget {
  const CreateTransactionSheet({
    super.key,
    required this.api,
    required this.preferences,
    required this.actions,
  });

  final AriaApi api;
  final Map<String, dynamic>? preferences;
  final List<String> actions;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Create transaction', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            if (actions.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 16),
                child: Text('No transaction permissions on this account.'),
              )
            else ...[
              if (actions.contains('sell'))
                ListTile(
                  leading: const Icon(Icons.point_of_sale),
                  title: const Text('Sell'),
                  onTap: () => _open(context, SellFormScreen(api: api, preferences: preferences)),
                ),
              if (actions.contains('buy'))
                ListTile(
                  leading: const Icon(Icons.shopping_cart_outlined),
                  title: const Text('Buy'),
                  onTap: () => _open(context, BuyFormScreen(api: api, preferences: preferences)),
                ),
              if (actions.contains('move'))
                ListTile(
                  leading: const Icon(Icons.swap_horiz),
                  title: const Text('Move stock'),
                  onTap: () => _open(context, MoveFormScreen(api: api, preferences: preferences)),
                ),
              if (actions.contains('cash_in'))
                ListTile(
                  leading: const Icon(Icons.account_balance_wallet),
                  title: const Text('Cash in'),
                  onTap: () => _open(context, CashFormScreen(api: api, cashIn: true, preferences: preferences)),
                ),
              if (actions.contains('cash_out'))
                ListTile(
                  leading: const Icon(Icons.payments_outlined),
                  title: const Text('Cash out'),
                  onTap: () => _open(context, CashFormScreen(api: api, cashIn: false, preferences: preferences)),
                ),
              if (actions.contains('transfer'))
                ListTile(
                  leading: const Icon(Icons.compare_arrows),
                  title: const Text('Transfer'),
                  onTap: () => _open(context, TransferFormScreen(api: api, preferences: preferences)),
                ),
            ],
          ],
        ),
      ),
    );
  }

  void _open(BuildContext context, Widget screen) {
    Navigator.pop(context);
    Navigator.push(
      context,
      MaterialPageRoute<void>(builder: (c) => screen),
    );
  }
}
