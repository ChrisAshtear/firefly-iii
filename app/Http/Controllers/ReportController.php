<?php namespace FireflyIII\Http\Controllers;

use Carbon\Carbon;
use Exception;
use FireflyIII\Helpers\Report\ReportHelperInterface;
use FireflyIII\Helpers\Report\ReportQueryInterface;
use FireflyIII\Models\Account;
use FireflyIII\Models\Preference;
use FireflyIII\Models\TransactionJournal;
use Preferences;
use Session;
use Steam;
use View;
use Crypt;

/**
 * Class ReportController
 *
 * @package FireflyIII\Http\Controllers
 */
class ReportController extends Controller
{

    /** @var ReportHelperInterface */
    protected $helper;
    /** @var ReportQueryInterface */
    protected $query;

    /**
     * @param ReportHelperInterface $helper
     * @param ReportQueryInterface  $query
     */
    public function __construct(ReportHelperInterface $helper, ReportQueryInterface $query)
    {
        $this->query  = $query;
        $this->helper = $helper;

        View::share('title', 'Reports');
        View::share('mainTitleIcon', 'fa-line-chart');

    }

    /**
     * @param string $year
     * @param string $month
     *
     * @return \Illuminate\View\View
     */
    public function budget($year = '2014', $month = '1')
    {
        try {
            new Carbon($year . '-' . $month . '-01');
        } catch (Exception $e) {
            return view('error')->with('message', 'Invalid date');
        }
        $date  = new Carbon($year . '-' . $month . '-01');
        $start = clone $date;
        $start->startOfMonth();
        $end = clone $date;
        $end->endOfMonth();
        $start->subDay();

        /** @var Preference $pref */
        $pref              = Preferences::get('showSharedReports', false);
        $showSharedReports = $pref->data;


        $dayEarly     = clone $date;
        $subTitle     = 'Budget report for ' . $date->format('F Y');
        $subTitleIcon = 'fa-calendar';
        $dayEarly     = $dayEarly->subDay();
        $accounts     = $this->query->getAllAccounts($start, $end, $showSharedReports);
        $start->addDay();

        $accounts->each(
            function (Account $account) use ($start, $end) {
                $budgets        = $this->query->getBudgetSummary($account, $start, $end);
                $balancedAmount = $this->query->balancedTransactionsSum($account, $start, $end);
                $array          = [];
                $hide           = true;
                foreach ($budgets as $budget) {
                    $id         = intval($budget->id);
                    $data       = $budget->toArray();
                    $array[$id] = $data;
                    if (floatval($data['amount']) != 0) {
                        $hide = false;
                    }
                }
                $account->hide              = $hide;
                $account->budgetInformation = $array;
                $account->balancedAmount    = $balancedAmount;

            }
        );

        /**
         * Start getBudgetsForMonth DONE
         */
        $budgets = $this->helper->getBudgetsForMonth($date, $showSharedReports);

        /**
         * End getBudgetsForMonth DONE
         */

        return view('reports.budget', compact('subTitle', 'year', 'month', 'subTitleIcon', 'date', 'accounts', 'budgets', 'dayEarly'));

    }

    /**
     * @param ReportHelperInterface $helper
     *
     * @return View
     */
    public function index()
    {
        $start         = Session::get('first');
        $months        = $this->helper->listOfMonths($start);
        $years         = $this->helper->listOfYears($start);
        $title         = 'Reports';
        $mainTitleIcon = 'fa-line-chart';

        return view('reports.index', compact('years', 'months', 'title', 'mainTitleIcon'));
    }

    /**
     * @param Account $account
     * @param string  $year
     * @param string  $month
     *
     * @return \Illuminate\View\View
     */
    public function modalBalancedTransfers(Account $account, $year = '2014', $month = '1')
    {

        try {
            new Carbon($year . '-' . $month . '-01');
        } catch (Exception $e) {
            return view('error')->with('message', 'Invalid date');
        }
        $start = new Carbon($year . '-' . $month . '-01');
        $end   = clone $start;
        $end->endOfMonth();

        $journals = $this->query->balancedTransactionsList($account, $start, $end);

        return view('reports.modal-journal-list', compact('journals'));


    }

    /**
     * @param Account              $account
     * @param string               $year
     * @param string               $month
     * @param ReportQueryInterface $query
     *
     * @return View
     */
    public function modalLeftUnbalanced(Account $account, $year = '2014', $month = '1')
    {
        try {
            new Carbon($year . '-' . $month . '-01');
        } catch (Exception $e) {
            return view('error')->with('message', 'Invalid date');
        }
        $start = new Carbon($year . '-' . $month . '-01');
        $end   = clone $start;
        $end->endOfMonth();
        $set = $this->query->getTransactionsWithoutBudget($account, $start, $end);

        $journals = $set->filter(
            function (TransactionJournal $journal) {
                $count = $journal->transactiongroups()->where('relation', 'balance')->count();
                if ($count == 0) {
                    return $journal;
                }
            }
        );

        return view('reports.modal-journal-list', compact('journals'));
    }

    /**
     * @param Account $account
     * @param string  $year
     * @param string  $month
     *
     * @return \Illuminate\View\View
     */
    public function modalNoBudget(Account $account, $year = '2014', $month = '1')
    {
        try {
            new Carbon($year . '-' . $month . '-01');
        } catch (Exception $e) {
            return view('error')->with('message', 'Invalid date');
        }
        $start = new Carbon($year . '-' . $month . '-01');
        $end   = clone $start;
        $end->endOfMonth();
        $journals = $this->query->getTransactionsWithoutBudget($account, $start, $end);

        return view('reports.modal-journal-list', compact('journals'));

    }

    /**
     * @param string $year
     * @param string $month
     *
     * @return \Illuminate\View\View
     */
    public function month($year = '2014', $month = '1')
    {
        try {
            new Carbon($year . '-' . $month . '-01');
        } catch (Exception $e) {
            return view('error')->with('message', 'Invalid date.');
        }
        $date         = new Carbon($year . '-' . $month . '-01');
        $subTitle     = 'Report for ' . $date->format('F Y');
        $subTitleIcon = 'fa-calendar';
        $displaySum   = true; // to show sums in report.
        /** @var Preference $pref */
        $pref              = Preferences::get('showSharedReports', false);
        $showSharedReports = $pref->data;


        /**
         *
         * get income for month (date)
         *
         */

        $start = clone $date;
        $start->startOfMonth();
        $end = clone $date;
        $end->endOfMonth();

        /**
         * Start getIncomeForMonth DONE
         */
        $income = $this->query->incomeByPeriod($start, $end, $showSharedReports);
        /**
         * End getIncomeForMonth DONE
         */
        /**
         * Start getExpenseGroupedForMonth DONE
         */
        $set      = $this->query->journalsByExpenseAccount($start, $end, $showSharedReports);

        $expenses = Steam::makeArray($set);
        $expenses = Steam::sortArray($expenses);
        $expenses = Steam::limitArray($expenses, 10);
        /**
         * End getExpenseGroupedForMonth DONE
         */
        /**
         * Start getBudgetsForMonth DONE
         */
        $budgets = $this->helper->getBudgetsForMonth($date, $showSharedReports);

        /**
         * End getBudgetsForMonth DONE
         */
        /**
         * Start getCategoriesForMonth DONE
         */
        // all categories.
        $result     = $this->query->journalsByCategory($start, $end);
        $categories = Steam::makeArray($result);


        // all transfers
        if ($showSharedReports === false) {
            $result    = $this->query->sharedExpensesByCategory($start, $end);
            $transfers = Steam::makeArray($result);
            $merged    = Steam::mergeArrays($categories, $transfers);
        } else {
            $merged = $categories;
        }


        // sort.
        $sorted = Steam::sortNegativeArray($merged);

        // limit to $limit:
        $categories = Steam::limitArray($sorted, 10);
        /**
         * End getCategoriesForMonth DONE
         */
        /**
         * Start getAccountsForMonth
         */
        $list     = $this->query->accountList($showSharedReports);
        $accounts = [];
        /** @var Account $account */
        foreach ($list as $account) {
            $id = intval($account->id);
            /** @noinspection PhpParamsInspection */
            $accounts[$id] = [
                'name'         => $account->name,
                'startBalance' => Steam::balance($account, $start),
                'endBalance'   => Steam::balance($account, $end)
            ];

            $accounts[$id]['difference'] = $accounts[$id]['endBalance'] - $accounts[$id]['startBalance'];
        }

        /**
         * End getAccountsForMonth
         */


        return view(
            'reports.month',
            compact(
                'income', 'expenses', 'budgets', 'accounts', 'categories',
                'date', 'subTitle', 'displaySum', 'subTitleIcon'
            )
        );
    }

    /**
     * @param $year
     *
     * @return $this
     */
    public function year($year)
    {
        try {
            new Carbon('01-01-' . $year);
        } catch (Exception $e) {
            return view('error')->with('message', 'Invalid date.');
        }
        /** @var Preference $pref */
        $pref              = Preferences::get('showSharedReports', false);
        $showSharedReports = $pref->data;
        $date              = new Carbon('01-01-' . $year);
        $end               = clone $date;
        $end->endOfYear();
        $title           = 'Reports';
        $subTitle        = $year;
        $subTitleIcon    = 'fa-bar-chart';
        $mainTitleIcon   = 'fa-line-chart';
        $balances        = $this->helper->yearBalanceReport($date, $showSharedReports);
        $groupedIncomes  = $this->query->journalsByRevenueAccount($date, $end, $showSharedReports);
        $groupedExpenses = $this->query->journalsByExpenseAccount($date, $end, $showSharedReports);

        return view(
            'reports.year', compact('date', 'groupedIncomes', 'groupedExpenses', 'year', 'balances', 'title', 'subTitle', 'subTitleIcon', 'mainTitleIcon')
        );
    }


}
